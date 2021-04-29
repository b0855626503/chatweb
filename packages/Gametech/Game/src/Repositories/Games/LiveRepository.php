<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\Http;

class LiveRepository extends Repository
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
        $game = 'live22';

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

        return Http::timeout(15)->withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store'
        ])->withOptions([
            'debug' => false,
            'verify' => false
        ])->post($url, $param);

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
        $return['msg'] = 'Error';

        $user_pass = "Aa" . rand(100000, 999999);
        $param = [
            'agent_id' => $this->agent,
            'password' => $this->agentPass,
            'preset_password' => $user_pass,
            'client_ip' => request()->server('SERVER_ADDR')
        ];

        $response = $this->GameCurl($param, 'createmember');

        if ($this->debug) {
            return $this->Debug($response);

        }

        if ($response->successful()) {
            $response = $response->json();


            if ($response['success'] == true) {

                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['user_name'] = $response['player_id'];
                $return['user_pass'] = $response['player_password'];

            }
        }

        return $return;
    }


    public function changePass($data): array
    {
        $return['success'] = false;


        $param = [
            'agent_id' => $this->agent,
            'password' => $this->agentPass,
            'player_id' => $data['user_name'],
            'new_password' => $data['user_pass'],
            'client_ip' => request()->server('SERVER_ADDR')
        ];

        $response = $this->GameCurl($param, 'updatepassword');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
                $return['msg'] = 'Complete';
                $return['success'] = true;

            } else {
                $return['msg'] = $return['error'];

            }
        } else {
            $return['msg'] = $return['error'];
        }

        return $return;
    }

    public function viewBalance($username): array
    {
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'agent_id' => $this->agent,
            'password' => $this->agentPass,
            'player_id' => $username,
            'client_ip' => request()->server('SERVER_ADDR')
        ];

        $response = $this->GameCurl($param, 'getbalance');

        if ($this->debug) {
            $return = $this->Debug($response);
        }

        if ($response->successful()) {
            $response = $response->json();

            if ($response['success'] === true) {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['score'] = $response['balance'];

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
                'agent_id' => $this->agent,
                'password' => $this->agentPass,
                'player_id' => $username,
                'amount' => $score,
                'client_ip' => request()->server('SERVER_ADDR')
            ];

            $response = $this->GameCurl($param, 'deposit');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['success'] === true) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = $response['after_balance'];
                    $return['before'] = $response['before_balance'];
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
                'agent_id' => $this->agent,
                'password' => $this->agentPass,
                'player_id' => $username,
                'amount' => $score,
                'client_ip' => request()->server('SERVER_ADDR')
            ];

            $response = $this->GameCurl($param, 'withdrawal');

            if ($this->debug) {
                $return = $this->Debug($response);
            }

            if ($response->successful()) {
                $response = $response->json();

                if ($response['success'] === true) {
                    $return['success'] = true;
                    $return['ref_id'] = $transID;
                    $return['after'] = $response['after_balance'];
                    $return['before'] = $response['before_balance'];
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
