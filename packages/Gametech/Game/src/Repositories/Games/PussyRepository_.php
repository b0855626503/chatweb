<?php

namespace Gametech\Game\Repositories\Games;

use Illuminate\Support\Facades\Http;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Http\Client\RequestException;

class PussyRepository_ extends Repository
{

    public function addGameAccount($data)
    {
        $account = $this->newUser();
        if(!$account){
            $response['success'] = false;
        }
        $response = $this->addUser($account,$data);
        return $response;
    }

    public function newUser()
    {

        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower(config('game.pussy.login') . config('game.pussy.auth') . config('game.pussy.agent') . $time . config('game.pussy.secretkey'))));
        $param = [
            'action' => 'RandomAccount',
            'userName' => config('game.pussy.agent'),
            'loginUser' => config('game.pussy.login'),
            'UserAreaId' => '2',
            'authcode' => config('game.pussy.auth'),
            'time' => $time,
            'sign' => $sign
        ];

        try {
            $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/account.ashx', $param)->throw()->json();
        } catch (RequestException $e) {
            $response = '';
        }

        if(!$response['account']){
            $response['account'] = '';
        }

        return $response['account'];
    }

    public function addUser($username,$data)
    {
        $user_pass = "Aa" . rand(100000, 999999);
        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower(config('game.pussy.auth') . $username . $time . config('game.pussy.secretkey'))));
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
            'agent' => config('game.pussy.agent'),
            'authcode' => config('game.pussy.auth'),
            'time' => $time,
            'sign' => $sign
        ];

        try {
            $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/account.ashx', $param)->throw()->json();
        } catch (RequestException $e) {
            $response['success'] = false;
        }

        if($response['success'] !== false){
            $response['success'] = true;
            $response['user_name'] = $username;
            $response['user_pass'] = $user_pass;
        }

        return $response;
    }

    public function changePass($data)
    {

        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower(config('game.pussy.auth') . $data['user_name'] . $time . config('game.pussy.secretkey'))));

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
            'agent' => config('game.pussy.agent'),
            'authcode' => config('game.pussy.auth'),
            'time' => $time,
            'sign' => $sign
        ];

        try {
            $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/account.ashx', $param)->throw()->json();
        } catch (RequestException $e) {
            $response['success'] = false;
        }

        if($response['success'] !== false){
            $response['success'] = true;
        }

        return $response;
    }

    public function viewBalance($username)
    {
//        dd($username);
        $time = round(microtime(true) * 1000);
        $sign = strtoupper(md5(strtolower(config('game.pussy.auth') . $username . $time . config('game.pussy.secretkey'))));

        $param = [
            'action' => 'getUserInfo',
            'userName' => $username,
            'authcode' => config('game.pussy.auth'),
            'time' => $time,
            'sign' => $sign
        ];

        try {
            $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/account.ashx', $param)->throw()->json();
            if($response['success'] == false){
                $response['ScoreNum'] = 0;
            }
        } catch (RequestException $e) {
            $response['ScoreNum'] = 0;

        }

        return $response['ScoreNum'] * 10;
    }

    public function deposit($username, $amount)
    {
        $ip = request()->ip();
        $score = $amount / 10;
        if ($score < 0) {
            $response['success'] = false;
            $response['msg'] = "Error score";
        } elseif (empty($username)) {
            $response['success'] = false;
            $response['msg'] = "Unknown user";
        } else {
            $time = round(microtime(true) * 1000);
            $sign = strtoupper(md5(strtolower(config('game.pussy.auth') . $username . $time . config('game.pussy.secretkey'))));

            $param = array(
                'action' => 'setServerScore',
                'userName' => $username,
                'scoreNum' => $score,
                'ActionUser' => $username,
                'ActionIp' => $ip,
                'authcode' => config('game.pussy.auth'),
                'time' => $time,
                'sign' => $sign
            );
            try {
                $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/setScore.ashx', $param)->throw()->json();
            } catch (RequestException $e) {
                $response['success'] = false;
            }

            if($response['success'] !== false){
                $response['success'] = true;
                $response['ref_id'] = $response['acc'];
                $response['after']  = $response['money'] * 10;
                $response['before'] = $response['after'] - $amount;
            }

        }
        return $response;
    }

    public function withdraw($username, $amount)
    {
        $ip = request()->ip();
        $score = $amount / 10;
        if ($score < 0) {
            $response['success'] = false;
            $response['msg'] = "Error score";
        } elseif (empty($username)) {
            $response['success'] = false;
            $response['msg'] = "Unknown user";
        } else {
            $score = $score * -1;
            $time = round(microtime(true) * 1000);
            $sign = strtoupper(md5(strtolower(config('game.pussy.auth') . $username . $time . config('game.pussy.secretkey'))));

            $param = array(
                'action' => 'setServerScore',
                'userName' => $username,
                'scoreNum' => $score,
                'ActionUser' => $username,
                'ActionIp' => $ip,
                'authcode' => config('game.pussy.auth'),
                'time' => $time,
                'sign' => $sign
            );
            try {
                $response = Http::get(config('game.pussy.apiurl') . 'ashx/account/setScore.ashx', $param)->throw()->json();
            } catch (RequestException $e) {
                $response['success'] = false;
            }

            if($response['success'] !== false){
                $response['success'] = true;
                $response['ref_id'] = $response['acc'];
                $response['after']  = $response['money'] * 10;
                $response['before'] = $response['after'] + $amount;
            }

        }
        return $response;
    }



    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Game\Contracts\User';
    }
}
