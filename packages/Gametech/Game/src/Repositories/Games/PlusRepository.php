<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PlusRepository extends Repository
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
        $game = '100plus';

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
        $agentid = $this->agent;
        $agentpass = $this->agentPass;
        $secretkey = $this->secretkey;
        $key = $agentpass . $secretkey;

        $message = json_encode($param,JSON_UNESCAPED_UNICODE);
        $hash = hash_hmac('SHA256', $message, $key);


        $url = $this->url . $action . '?hash=' .$hash. '&from=' . $agentid . '&secret=' . $agentpass;


        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store'
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
            $account = '';
            $result = $this->addUser($account, $data);
        }

        return $result;
    }

    public function newUser(): array
    {
        $return['success'] = true;

        return $return;
    }

    public function addUser($username, $data): array
    {
        $return['success'] = false;

        $user_pass = "Aa" . rand(100000, 999999);
        $param = [
            'type' => 'player',
            'account' => $this->agent,
            'password' => $user_pass,
            'name' => $data['name'],
            'tel' => $data['tel'],
            'desc' => 'Player',
        ];

        $response = $this->GameCurl($param, 'addAccount');

        if ($this->debug) {
            $return = $this->Debug($response);
        }



        if ($response->successful()) {

            $response = $response->json();

            if ($response['status'] === 'success') {

                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['user_name'] = $response['account'];
                $return['user_pass'] = $user_pass;

            }
        }

        return $return;
    }

    public function changePass($data): array
    {
        $return['success'] = false;
        $return['msg'] = 'ไม่สามารถทำรายการได้';

        $param = [
            'account' => $data['user_name'],
            'password' => $data['user_pass'],
            'name' => $data['name'],
            'tel' => '0000000000',
            'desc' => 'Player',
        ];


        $response = $this->GameCurl($param, 'editAccount');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

//        dd($return);

        if ($response->successful()) {
            $response = $response->json();

            if ($response['status'] === 'success') {
                $return['success'] = true;
            }
            $return['msg'] = isset($response['msg']) ?: 'ทำรายการสำเร็จ';
        }

        return $return;
    }

    public function viewBalance($username): array
    {
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'account' => $username,
        ];


        $response = $this->GameCurl($param, 'getAccount');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['status'] === 'success') {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = $response['score'] * 10;

            }
        }

        return $return;
    }

    public function deposit($username, $amount): array
    {
        $return['success'] = false;

        $score = $amount / 10;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $transID = "DP" . date('YmdHis');
            $before = $this->viewBalance($username);

            $param = [
                'account' => $username,
                'setScore' => strval($score)
            ];

            $response = $this->GameCurl($param, 'setScore');

            if ($this->debug) {
                $return = $this->Debug($response);
            }


            if ($response->successful()) {
                $response = $response->json();

                if ($response['status'] === 'success') {

                    $after = $this->viewBalance($username);

                    $return['success'] = true;
                    $return['ref_id'] = (isset($response['acc']) ? : $transID);
                    $return['after'] = $after['score'];
                    $return['before'] = $before['score'];

                }
            }

        }

        return $return;
    }

    public function withdraw($username, $amount): array
    {
        $return['success'] = false;

        $score = $amount / 10;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
        } else {
            $transID = "WD" . date('YmdHis');
            $before = $this->viewBalance($username);

            $score = ($score * -1);

            $param = [
                'account' => $username,
                'setScore' => strval($score)
            ];

            $response = $this->GameCurl($param, 'setScore');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['status'] === 'success') {

                    $after = $this->viewBalance($username);

                    $return['success'] = true;
                    $return['ref_id'] = (isset($response['acc']) ? : $transID);
                    $return['after'] = $after['score'];
                    $return['before'] = $before['score'];

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
