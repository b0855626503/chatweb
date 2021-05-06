<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DreamtechRepository extends Repository
{
    protected $responses;

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

        $this->responses = [];

        parent::__construct($app);
    }

    public function Debug($response, $custom = false)
    {

        if (!$custom) {
            $return['body'] = $response->body();
            $return['json'] = $response->json();
            $return['successful'] = $response->successful();
            $return['failed'] = $response->failed();
            $return['clientError'] = $response->clientError();
            $return['serverError'] = $response->serverError();
        } else {
            $return['body'] = json_encode($response);
            $return['json'] = $response;
            $return['successful'] = 1;
            $return['failed'] = 1;
            $return['clientError'] = 1;
            $return['serverError'] = 1;
        }

        $this->responses[] = $return;


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

        $response = Http::timeout(15)->withHeaders([
            'Pass-Key' => $this->passkey,
            'Session-Id' => request()->getSession()->getId(),
            'Hash' => md5($postString),
        ])->asJson()->post($url, $param);

        if ($this->debug) {
            $this->Debug($response);
        }
        return $response;
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

        if ($this->debug) {
            $this->Debug($response);
        }

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

        $response = Http::withHeaders([

            'Pass-Key' => $this->passkey,
            'Session-Id' => request()->getSession()->getId(),
            'Hash' => md5($postString),
            'Auth-Key' => $key
        ])->asJson()->post($url, $param);

        if ($this->debug) {
            $this->Debug($response);
        }

        return $response;

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
        } else {

            $return['success'] = false;
            $return['msg'] = 'ไม่สามารถลงทะเบียนรหัสเกมได้ เนื่องจาก ID เกมหมด โปรดแจ้ง Staff';
        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true, 'account' => ''];
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


        $responses = $this->GameCurl($param, $username . '/authenticate');
        $response = $responses->json();

        if ($responses->successful()) {

            if ($response['code'] == 'UNKNOWN_ERROR') {

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


                $responses = $this->GameCurl($param, 'create-check-account');

                $response = $responses->json();

                if ($responses->successful()) {

                    if (!empty($response['platformLoginId'])) {
                        $return['msg'] = 'Complete';
                        $return['success'] = true;
                        $return['user_name'] = $username;
                        $return['user_pass'] = $user_pass;

                        $return['debug']['json'][]['user_name'] = $username;
                        $return['debug']['json'][]['user_pass'] = $user_pass;

                        DB::table('users_dreamtech')
                            ->where('user_name', $username)
                            ->update(['date_join' => now()->toDateString(), 'ip' => request()->ip(), 'use_account' => 'Y', 'user_update' => 'SYSTEM']);

                    } else {

                        DB::table('users_dreamtech')
                            ->where('user_name', $username)
                            ->update(['use_account' => 'Y']);

                        $return['msg'] = $response['message'];
                        $return['success'] = false;
                    }

                } else {

                    $return['msg'] = $response['message'];
                    $return['success'] = false;
                }

            } else {
                $return['msg'] = $response['message'];
                $return['success'] = false;
            }

        } else {

            $return['msg'] = $response['message'];
            $return['success'] = false;

        }


        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
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


            $responses = $this->GameCurlKey($param, $data['user_name'] . '/update-account', $key);

            $response = $responses->json();

            if ($responses->successful()) {
                $return['msg'] = 'เปลี่ยนรหัสผ่านเกม เรียบร้อย';
                $return['success'] = true;
            } else {

                $return['msg'] = $response['message'];
                $return['success'] = false;
            }
        } else {

            $return['success'] = false;
            $return['msg'] = 'เกิดข้อผิดพลาดในการ ตรวจสอบ ID Game จึงไม่สามารถทำรายการ เปลี่ยนรหัสได้';


        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
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

            $responses = $this->GameCurlKey($param, $username . '/balance', $key);

            $response = $responses->json();

            if ($responses->successful()) {

                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = doubleval($response['balance']);

            } else {

                $return['msg'] = $response['message'];
                $return['success'] = false;

            }

        } else {

            $return['success'] = false;
            $return['msg'] = 'เกิดข้อผิดพลาดในการ ตรวจสอบ ID Game จึงไม่สามารถทำรายการ ตรวจสอบยอดเงินได้';


        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }
        return $return;
    }

    public function deposit($username, $amount): array
    {
        $return['success'] = false;

        $score = $amount;

        if ($score < 0) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
            if ($this->debug) {
                $this->Debug($return, true);
            }
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

                $responses = $this->GameCurlKey($param, 'transaction', $key);

                $response = $responses->json();

                if ($responses->successful()) {

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

            } else {

                $return['success'] = false;
                $return['msg'] = 'เกิดข้อผิดพลาดในการ ตรวจสอบ ID Game จึงไม่สามารถทำรายการ ฝากเข้าได้';

            }

        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }
        return $return;
    }

    public function withdraw($username, $amount): array
    {
        $return['success'] = false;

        $score = $amount;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
            if ($this->debug) {
                $this->Debug($return, true);
            }
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

                $responses = $this->GameCurlKey($param, 'transaction', $key);

                $response = $responses->json();

                if ($responses->successful()) {

                    if (!empty($response['transactionReferenceNo'])) {
                        $return['success'] = true;
                        $return['ref_id'] = $response['transactionReferenceNo'];
                        $return['after'] = $response['balance'];
                        $return['before'] = ($response['balance'] + $score);

                    } else {
                        $return['success'] = false;
                        $return['msg'] = $response['message'];
                    }
                } else {
                    $return['success'] = false;
                    $return['msg'] = $response['message'];
                }
            } else {

                $return['success'] = false;
                $return['msg'] = 'เกิดข้อผิดพลาดในการ ตรวจสอบ ID Game จึงไม่สามารถทำรายการ ถอนออกได้';

            }

        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
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
