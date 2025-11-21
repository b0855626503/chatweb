<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\Http;

class PussyRepository extends Repository
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
        $game = 'pussy';

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

    public function GameCurl($param, $action)
    {

        $response =  rescue(function () use ($param, $action) {

            $url = $this->url . $action;


            return Http::timeout(15)->asForm()->post($url, $param);


        }, function ($e) {

            return $e->response;

        }, true);

        if ($this->debug) {
            $this->Debug($response);
        }

//        if($response === false){
//            $result['main'] = false;
//            $result['success'] = false;
//            $result['msg'] = 'เชื่อมต่อไม่ได้';
//            return $result;
//        }else{
//            $result['msg'] = '';
//        }

        $result = $response->json();

        if ($response->successful()) {
            $result['main'] = true;
        } else {
            $result['main'] = false;

        }
        return $result;


    }

    public function addGameAccount($data): array
    {
        $result = $this->newUser();
        if ($result['success'] === true) {
            $account = $result['account'];
            $result = $this->addUser($account, $data);
        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
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


        if ($response['main'] === true && $response['success'] === true) {
            $return['success'] = true;
            $return['account'] = $response['account'];
        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }


//        if ($this->debug) {
//            return ['debug' => $this->responses, 'success' => true, 'account' => ''];
//        }
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


        if ($response['main'] === true && $response['success'] === true) {

            $return['success'] = true;
            $return['user_name'] = $username;
            $return['user_pass'] = $user_pass;

        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }


//        if ($this->debug) {
//            return ['debug' => $this->responses, 'success' => true];
//        }
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


        if ($response['main'] === true && $response['success'] === true) {
            $return['msg'] = 'เปลี่ยนรหัสผ่านเกม เรียบร้อย';
            $return['success'] = true;
        } else {
            $return['success'] = false;
            $return['msg'] = $response['msg'];
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
//        dd($response);


        if ($response['main'] === true && $response['success'] === true) {
            $return['msg'] = 'Complete';
            $return['connect'] = true;
            $return['success'] = true;
            $score = ($response['ScoreNum'] * 10);
            $return['score'] = $score;
        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
            $return['connect'] = true;
        }


        if ($this->debug) {

            return ['debug' => $this->responses, 'success' => true];
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
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {
//            $time = round(microtime(true) * 1000);
            $time = now()->timestamp;
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


            if ($response['main'] === true && $response['success'] === true) {
                $return['success'] = true;
                $return['ref_id'] = $response['acc'];
                $return['after'] = ($response['money'] * 10);
                $return['before'] = ($return['after'] - $amount);
                $return['msg'] = 'Complete';
            } else {
                $return['success'] = false;
                $return['msg'] = $response['msg'];
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

        $ip = request()->ip();
        $score = $amount / 10;

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
            $score = $score * -1;

//            $time = round(microtime(true) * 1000);
            $time = now()->timestamp;
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


            if ($response['main'] === true && $response['success'] === true) {
                $return['success'] = true;
                $return['ref_id'] = $response['acc'];
                $return['after'] = ($response['money'] * 10);
                $return['before'] = ($return['after'] + $amount);
                $return['msg'] = 'Complete';
            } else {
                $return['success'] = false;
                $return['msg'] = $response['msg'];
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
