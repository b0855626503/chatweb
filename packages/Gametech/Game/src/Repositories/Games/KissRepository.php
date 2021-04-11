<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Http;

class KissRepository extends Repository
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
        $game = 'kiss';

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

        $url = $this->url . $action;

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

        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower($this->login . $this->auth . $this->agent . $time . $this->secretkey)));
        $param = [
            'action' => 'RandomAccount',
            'userName' => $this->agent,
            'loginUser' => $this->login,
            'UserAreaId' => '2',
            'authcode' => $this->auth,
            'time' => $time,
            'sign' => $sign
        ];

        $response = $this->GameCurl($param, 'ashx/account/account.ashx');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
                $return['success'] = true;
                $return['account'] = $response['account'];

            }
        }

        return $return;
    }

    public function addUser($username, $data): array
    {
        $return['success'] = false;

        $user_pass = "Aa" . rand(100000, 999999);
        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower($this->auth . $username . $time . $this->secretkey)));
        $param = [
            'action' => 'AddUser',
            'UserType' => 1,
            'PassWd' => $user_pass,
            'pwdtype' => 1,
            'userName' => $username,
            'Name' => $data['name'],
            'UserAreaId' => '2',
            'Tel' => 'N/A',
            'Memo' => 'N/A',
            'agent' => $this->agent,
            'authcode' => $this->auth,
            'time' => $time,
            'sign' => $sign
        ];

        $response = $this->GameCurl($param, 'ashx/account/account.ashx');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
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

        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower($this->auth . $data['user_name'] . $time . $this->secretkey)));

        $param = [
            'action' => 'editUser2',
            'UserType' => 1,
            'PassWd' => $data['user_pass'],
            'pwdtype' => 1,
            'userName' => $data['user_name'],
            'Name' => $data['name'],
            'Flag' => 1,
            'Tel' => 'N/A',
            'Memo' => 'N/A',
            'agent' => $this->agent,
            'authcode' => $this->auth,
            'time' => $time,
            'sign' => $sign
        ];

        $response = $this->GameCurl($param, 'ashx/account/account.ashx');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
                $return['success'] = true;

            }
        }


        return $return;
    }

    public function viewBalance($username): array
    {
        $return['success'] = false;
        $return['score'] = 0;


        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower($this->auth . $username . $time . $this->secretkey)));

        $param = [
            'action' => 'getUserInfo',
            'userName' => $username,
            'authcode' => $this->auth,
            'time' => $time,
            'sign' => $sign
        ];

        $response = $this->GameCurl($param, 'ashx/account/account.ashx');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $score = $response['ScoreNum'] * 10;
                $return['score'] = $score;
            }
        }

        return $return;
    }

    public function deposit($username, $amount): array
    {
        $return['success'] = false;

        $ip = request()->ip();
        $score = $amount / 10;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $time = round(microtime(true) * 1000);
            $sign = strtoupper(md5(strtolower($this->auth . $username . $time . $this->secretkey)));

            $param = [
                'action' => 'setServerScore',
                'userName' => $username,
                'scoreNum' => $score,
                'ActionUser' => $username,
                'ActionIp' => $ip,
                'authcode' => $this->auth,
                'time' => $time,
                'sign' => $sign
            ];

            $response = $this->GameCurl($param, 'ashx/account/setScore.ashx');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['success'] === true) {
                    $return['success'] = true;
                    $return['ref_id'] = $response['acc'];
                    $return['after'] = ($response['money'] * 10);
                    $return['before'] = ($return['after'] - $amount);
                }
            }

        }

        return $return;
    }

    public function withdraw($username, $amount): array
    {
        $return['success'] = false;

        $ip = request()->ip();
        $score = $amount / 10;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $score = $score * -1;
            $time = round(microtime(true) * 1000);
            $sign = strtoupper(md5(strtolower($this->auth . $username . $time . $this->secretkey)));

            $param = [
                'action' => 'setServerScore',
                'userName' => $username,
                'scoreNum' => $score,
                'ActionUser' => $username,
                'ActionIp' => $ip,
                'authcode' => $this->auth,
                'time' => $time,
                'sign' => $sign
            ];

            $response = $this->GameCurl($param, 'ashx/account/setScore.ashx');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['success'] === true) {
                    $return['success'] = true;
                    $return['ref_id'] = $response['acc'];
                    $return['after'] = ($response['money'] * 10);
                    $return['before'] = ($return['after'] + $amount);
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
