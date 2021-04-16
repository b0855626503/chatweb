<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PgslotRepository extends Repository
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

    public function __construct($method, $debug, App $app)
    {
        $game = 'pgslot';

        $this->method = $method;

        $this->debug = $debug;

        $this->url = config($this->method . '.' . $game . '.apiurl');

        $this->agent = config($this->method . '.' . $game . '.agent');

        $this->agentPass = config($this->method . '.' . $game . '.agent_pass');

        $this->login = config($this->method . '.' . $game . '.login');

        $this->auth = config($this->method . '.' . $game . '.auth');

        $this->passkey = config($this->method . '.' . $game . '.passkey');

        $this->secretkey = config($this->method . '.' . $game . '.secretkey');

        parent::__construct($app);
    }

    public function GameCurl($param, $action)
    {
        $url = $this->url . $action;

        $postString = json_encode($param);
        $hash = hash_pbkdf2("sha512", $postString, $this->secretkey, 1000, 64, true);
        $signature = base64_encode($hash);

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'x-amb-signature' => $signature,
        ])->post($url, $param);

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

        $response = DB::table('users_pgslot')
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
            'username' => $username,
            'password' => $user_pass,
            'agent' => $this->agent
        ];

        $response = $this->GameCurl($param, 'partner/create');

        if ($this->debug) {
            $return = $this->Debug($response);
        }


        if ($response->successful()) {
            $response = $response->json();


            if ($response['status']['code'] === 0) {
                DB::table('users_pgslot')
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
            'username' => $data['user_name'],
            'newPassword' => $data['user_pass'],
            'agent' => $this->agent
        ];

        $response = $this->GameCurl($param, 'partner/password');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['status']['code'] === 0) {
                $return['msg'] = $response['status']['message'];
                $return['success'] = true;

            }
        }

        return $return;
    }

    public function viewBalance($username): array
    {
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'username' => $username,
            'agent' => $this->agent
        ];

        $response = $this->GameCurl($param, 'partner/balance');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['status']['code'] === 0) {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = doubleval($response['data']['balance']);

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
            $transID = "DP" . date('YmdHis');
            $param = [
                'username' => $username,
                'amount' => $score,
                'agent' => $this->agent
            ];

            $response = $this->GameCurl($param, 'partner/deposit');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['status']['code'] === 0) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = doubleval($response['data']['balance']['after']);
                    $return['before'] = doubleval($response['data']['balance']['before']);
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

            $transID = "WD" . date('YmdHis');
            $param = [
                'username' => $username,
                'amount' => $score,
                'agent' => $this->agent
            ];

            $response = $this->GameCurl($param, 'partner/withdraw');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['status']['code'] === 0) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = doubleval($response['data']['balance']['after']);
                    $return['before'] = doubleval($response['data']['balance']['before']);
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
