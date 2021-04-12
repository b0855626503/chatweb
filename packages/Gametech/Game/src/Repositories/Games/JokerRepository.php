<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class JokerRepository extends Repository
{
    protected $method;

    protected $debug;

    protected $url;

    protected $agent;

    protected $agentPass;

    protected $passkey;

    protected $secretkey;

    protected $login;

    protected $auth;

    public function __construct($method, $debug)
    {
        $game = 'joker';

        $this->method = $method;

        $this->debug = $debug;

        $this->url = config($this->method . '.' . $game . '.apiurl');

        $this->agent = config($this->method . '.' . $game . '.agent');

        $this->agentPass = config($this->method . '.' . $game . '.agent_pass');

        $this->login = config($this->method . '.' . $game . '.login');

        $this->auth = config($this->method . '.' . $game . '.auth');

        $this->passkey = config($this->method . '.' . $game . '.passkey');

        $this->secretkey = config($this->method . '.' . $game . '.secretkey');
    }


    public function Debug($response)
    {

        $return['debug']['body'][] = $response->body();
        $return['debug']['json'][] = $response->json();
        $return['debug']['successful'][] = $response->successful();
        $return['debug']['failed'][] = $response->failed();
        $return['debug']['clientError'][] = $response->clientError();
        $return['debug']['serverError'][] = $response->serverError();

        return $return;
    }

    public function GameCurl($param, $action)
    {

        $postString = Arr::query($param);

        $signature = base64_encode(hash_hmac("sha1", $postString, $this->secretkey, true));
        $signature = urlencode($signature);
        $url = $this->url . "?AppID=" . $this->login . "&Signature=$signature";

        return Http::asForm()->post($url, $param);


    }

    public function addGameAccount($data): array
    {
        $result = $this->newUser();
        if ($result['success'] === true) {
            $account = $result['account'];
            $result = $this->addUser($account, $data);
        }

        return $result;
    }

    public function newUser(): array
    {
        $return['success'] = false;
        if ($this->method === 'game') {
            $free = 'N';
        } else {
            $free = 'Y';
        }

        $response = DB::table('users_joker')
            ->where('use_account', 'N')
            ->where('enable', 'Y')
            ->where('code', '<>', 0)
            ->where('freecredit', $free)
            ->select('user_name')
            ->inRandomOrder();

        if ($response->exists()) {
            $return['success'] = true;
            $return['account'] = $response->first()->user_name;
        }


        return $return;
    }

    public function addUser($username, $data): array
    {
        $return['success'] = false;

        $user_pass = "Aa" . rand(100000, 999999);
        $param = [
            'Method' => 'CU',
            'Timestamp' => time(),
            'Username' => $username
        ];

        $response = $this->GameCurl($param, '');

        if ($this->debug) {
            $return = $this->Debug($response);
        }


        if ($response->successful()) {

            $response = $response->json();

            if ($response['Status'] === 'Created') {
                $this->changePass([
                    'user_name' => $username,
                    'user_pass' => $user_pass
                ]);

                DB::table('users_joker')
                    ->where('user_name', $username)
                    ->update(['date_join' => now()->toDateString(), 'ip' => request()->ip(), 'use_account' => 'Y', 'user_update' => 'SYSTEM']);

                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['user_name'] = $username;
                $return['user_pass'] = $user_pass;

            }
        }

        return $return;
    }

    public function changePass($data): array
    {
        $return['success'] = false;

        $param = [
            'Method' => 'SP',
            'Password' => $data['user_pass'],
            'Timestamp' => time(),
            'Username' => $data['user_name']
        ];

        $response = $this->GameCurl($param, '');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {

            $response = $response->json();

            if ($response['Status'] === 'OK') {
                $return['msg'] = 'Complete';
                $return['success'] = true;

            }
        }

        return $return;
    }

    public function viewBalance($username): array
    {
        $return['success'] = false;
        $return['score']  = 0;

        $param = [
            'Method' => 'GC',
            'Timestamp' => time(),
            'Username' => $username
        ];



        $response = $this->GameCurl($param, '');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['Username'] === $username) {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = $response['Credit'];

            }
        }

        return $return;
    }

    public function deposit($username, $amount): array
    {
        $return['success'] = false;

        $score = $amount;

        if ($score < 0) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $transID = "DP" . date('YmdHis') . rand(100, 999);
            $param = [
                'Amount' => $score,
                'Method' => 'TC',
                'RequestID' => $transID,
                'Timestamp' => time(),
                'Username' => $username
            ];


            $response = $this->GameCurl($param, '');


            if ($this->debug) {
                $return = $this->Debug($response);
            }


            if ($response->successful()) {
                $response = $response->json();

                if ($response['Username'] === $username) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = $response['Credit'];
                    $return['before'] = $response['BeforeCredit'];

                }
            }

        }

        return $return;
    }

    public function withdraw($username, $amount): array
    {
        $return['success'] = false;


        $score = $amount;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $score = $score * -1;
            $transID = "WD" . date('YmdHis') . rand(100, 999);
            $param = [
                'Amount' => $score,
                'Method' => 'TC',
                'RequestID' => $transID,
                'Timestamp' => time(),
                'Username' => $username
            ];

            $response = $this->GameCurl($param, '');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['Username'] == $username) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = $response['Credit'];
                    $return['before'] = $response['BeforeCredit'];
                }
            }

        }

        return $return;
    }


    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Game\Contracts\User';
    }
}
