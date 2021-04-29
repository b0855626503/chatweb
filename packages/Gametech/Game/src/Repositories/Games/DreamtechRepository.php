<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DreamtechRepository extends Repository
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
        $game = 'dreamtech';

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

    public function Debug($response): array
    {
        $return['debug']['body'][] = $response->body();
        $return['debug']['json'][] = $response->json();
        $return['debug']['successful'][] = $response->successful();
        $return['debug']['failed'][] = $response->failed();
        $return['debug']['clientError'][] = $response->clientError();
        $return['debug']['serverError'][] = $response->serverError();

        return $return;
    }


    public function GameCurl($param, $action): Response
    {

        ksort($param);
        $postString = "";
        foreach ($param as $keyR => $value) {
            $postString .= $keyR . '=' . $value . '&';
        }
        $postString = substr($postString, 0, -1);

        $url = $this->url . $action;

        return Http::timeout(15)->withHeaders([
            'Pass-Key' => $this->passkey,
            'Session-Id' => request()->getSession()->getId(),
            'Hash' => md5($postString),
        ])->asJson()->post($url, $param);

    }

    public function GameCurlAuth($username): array
    {
        $return['success'] = false;

        $param = [
            'accountType' => 1,
            'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s')
        ];

        ksort($param);
        $postString = "";
        foreach ($param as $keyR => $value) {
            $postString .= $keyR . '=' . $value . '&';
        }
        $postString = substr($postString, 0, -1);

        $url = $this->url . $username . '/authenticate';

        $response = Http::withHeaders([

            'Pass-Key' => $this->passkey,
            'Session-Id' => request()->getSession()->getId(),
            'Hash' => md5($postString),
        ])->asJson()->post($url, $param);


        if ($response->successful()) {
            $response = $response->json();

            if (!empty($response['Auth-Key'])) {
                $return['success'] = true;
                $return['key'] = $response['Auth-Key'];
            }

        }

        return $return;
    }

    public function GameCurlKey($param, $action, $key): Response
    {
        ksort($param);
        $postString = "";
        foreach ($param as $keyR => $value) {
            $postString .= $keyR . '=' . $value . '&';
        }
        $postString = substr($postString, 0, -1);

        $url = $this->url . $action;

        return Http::withHeaders([

            'Pass-Key' => $this->passkey,
            'Session-Id' => request()->getSession()->getId(),
            'Hash' => md5($postString),
            'Auth-Key' => $key
        ])->asJson()->post($url, $param);


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

        $response = DB::table('users_dreamtech')
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

        $param = [
            'accountType' => 1,
            'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s')
        ];


        $response = $this->GameCurl($param, $username . '/authenticate');

        if ($this->debug) {
            return $this->Debug($response);

        }



        if ($response->successful()) {

            $response = $response->json();

            if (isset($response['code']) == 'UNKNOWN_ERROR') {

                $user_pass = "Aa" . rand(100000, 999999);
                $param = [
                    'accountStatus' => 1,
                    'accountType' => 1,
                    'agentLoginName' => $this->agent,
                    'balance' => 0,
                    'birthDate' => $data['birth_day'],
                    'email' => '',
                    'firstName' => $data['firstname'],
                    'gender' => $data['gender'],
                    'lastName' => $data['lastname'],
                    'loginName' => $username,
                    'mode' => 'real',
                    'password' => $user_pass,
                    'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s')
                ];


                $response = $this->GameCurl($param, 'create-check-account');


                if ($this->debug) {
                    $return = $this->Debug($response);
                }

                if ($response->successful()) {

                    $response = $response->json();

                    if ($response['platformLoginId']) {
                        $return['msg'] = 'Complete';
                        $return['success'] = true;
                        $return['user_name'] = $username;
                        $return['user_pass'] = $user_pass;

                        $return['debug']['json'][]['user_name'] = $username;
                        $return['debug']['json'][]['user_pass'] = $user_pass;

                        DB::table('users_dreamtech')
                            ->where('user_name', $username)
                            ->update(['date_join' => now()->toDateString(), 'ip' => request()->ip(), 'use_account' => 'Y', 'user_update' => 'SYSTEM']);

                    }


                }

            } else {
                $return['msg'] = $response['message'];
                $return['success'] = false;
            }
        }

        return $return;
    }

    public function changePass($data): array
    {
        $return['success'] = false;


        $response = $this->GameCurlAuth($data['user_name']);

        if ($response['success'] === true) {

            $key = $response['key'];
            $param = [
                'firstName' => $data['name'],
                'lastName' => '',
                'password' => $data['user_pass'],
                'email' => '',
                'birthDate' => $data['date_regis'],
                'gender' => $data['gender'],
                'mode' => 'real',
                'accountStatus' => 1,
                'accountType' => 1,
                'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s')
            ];


            $response = $this->GameCurlKey($param, $data['user_name'] . '/update-account', $key);

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $return['msg'] = 'Complete';
                $return['success'] = true;
            }
        }


        return $return;
    }

    public function viewBalance($username): array
    {

        $return['success'] = false;
        $return['score'] = 0;

        $response = $this->GameCurlAuth($username);

        if ($response['success'] === true) {

            $key = $response['key'];

            $param = [
                'accountType' => 1,
                'loginName' => $username,
                'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s')
            ];

            $response = $this->GameCurlKey($param, $username . '/balance', $key);

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = doubleval($response['balance']);

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

            $return = $this->GameCurlAuth($username);

            if ($return['success'] === true) {
                $key = $return['key'];

                $param = [
                    'accountType' => 1,
                    'agentKey' => '',
                    'amount' => $score,
                    'loginName' => $username,
                    'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s'),
                    'transactionNo' => '',
                    'txnType' => 'DEPOSIT'
                ];

                $response = $this->GameCurlKey($param, 'transaction', $key);

                if ($this->debug) {
                    $return = $this->Debug($response);
                }

                if ($response->successful()) {
                    $response = $response->json();


                    if (!empty($response['transactionReferenceNo'])) {
                        $return['success'] = true;
                        $return['ref_id'] = $response['transactionReferenceNo'];
                        $return['after'] = $response['balance'];
                        $return['before'] = ($response['balance'] - $score);

                    } else {
                        $return['success'] = false;
                        $return['msg'] = $response['message'];
                    }

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

            $return = $this->GameCurlAuth($username);

            if ($return['success'] === true) {
                $key = $return['key'];

                $param = [
                    'accountType' => 1,
                    'agentKey' => '',
                    'amount' => $score,
                    'loginName' => $username,
                    'timeStamp' => date('Y-m-d') . 'T' . date('H:i:s'),
                    'transactionNo' => '',
                    'txnType' => 'WITHDRAW'
                ];

                $response = $this->GameCurlKey($param, 'transaction', $key);

                if ($this->debug) {
                    $return = $this->Debug($response);
                }

                if ($response->successful()) {

                    $response = $response->json();

                    if (!empty($response['transactionReferenceNo'])) {
                        $return['success'] = true;
                        $return['ref_id'] = $response['transactionReferenceNo'];
                        $return['after'] = $response['balance'];
                        $return['before'] = ($response['balance'] + $score);

                    } else {
                        $return['success'] = false;
                        $return['msg'] = $response['message'];
                    }
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
