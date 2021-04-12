<?php

namespace Gametech\Game\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Throwable;

/**
 * Class GameUserRepository
 * @package Gametech\Game\Repositories
 */
class GameUserFreeRepository extends Repository
{

    protected $gameMethod;

    private $gameRepository;

    /**
     * GameRepository constructor.
     * @param GameRepository $gameRepo
     * @param App $app
     */
    public function __construct
    (
        GameRepository $gameRepo,
        App $app
    )
    {
        $this->gameRepository = $gameRepo;
        $this->gameMethod = 'gamefree';

        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Game\Contracts\GameUserFree';
    }


    public function getOneUser($id, $game, $update = true): array
    {

        $return['success'] = false;
        $return['msg'] = 'พบปัญหาบางประการ โปลดลองใหม่อีกครั้ง';

        $result = $this->with(['game' => function ($query) {
            $query->active()->open()->select('code', 'id', 'filepic', 'game_type', 'name');
        }])->where('enable', 'Y')->where('game_code', $game)->where('member_code', $id)->first();

        if (empty($result)) {
            return $return;
        }

        if ($update) {

            $response = $this->checkBalance($result->game->id, $result->user_name);

            if ($response['success'] == true) {

                $result->balance = $response['score'];
                $result->save();

            }


        }

        $return['success'] = true;
        $return['msg'] = 'Complete';
        $return['data'] = $result;
        return $return;

    }

    public function getUser($id, $getall = false, $getturn = false, $withdraw = false)
    {

        $results = $this->gameRepository->orderBy('sort')->findWhere(['status_open' => 'Y', 'enable' => 'Y', ['filepic', '<>', '']], array('code', 'id', 'filepic', 'game_type', 'name'));

        foreach ($results as $i => $result) {
            $game_user = $this->findOneWhere(['member_code' => $id, 'game_code' => $result->code, 'enable' => 'Y']);
            $game_id = preg_replace('/\d/', '', $result->id);
            $game = ucfirst($game_id);
            if (!is_null($game_user)) {
                if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {
                    $response = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod])->viewBalance($game_user->user_name);
                    if ($response['success'] === true) {

                        $score = $response['score'];
                        $game_user->balance = $score;
                        $game_user->save();

                    } else {

                        $score = $game_user->balance;

                    }


                    $results[$i]->user_code = $game_user->code;
                    $results[$i]->user_name = $game_user->user_name;
                    $results[$i]->balance = number_format($score, 2);

                } else {

                    $results[$i]->user_code = $game_user->code;
                    $results[$i]->user_name = $game_user->user_name;
                    $results[$i]->balance = number_format($game_user->balance, 2);
                }


            } else {
                if ($getall === false) {
                    unset($results[$i]);
                } else {
                    $results[$i]->user_code = '';
                    $results[$i]->user_name = '';
                    $results[$i]->balance = number_format(0, 2);

                }
            }
        }

        return $results;
    }

    public function addGameUser($game_code, $member_code, $data, $debug = false): array
    {
        $return['success'] = false;

        $games = $this->gameRepository->findOneByField('code', $game_code);
        $game_id = preg_replace('/\d/', '', $games->id);
        $game = ucfirst($game_id);
        if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {
            $result = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->addGameAccount($data);

            if ($debug) {
                return $result;
            }

            if ($result['success'] === true) {

                $param = [
                    'game_code' => $game_code,
                    'member_code' => $member_code,
                    'user_name' => $result['user_name'],
                    'user_pass' => $result['user_pass'],
                    'balance' => '0',
                    'user_create' => $data['user_create'],
                    'user_update' => $data['user_update']
                ];

                try {
                    $result = $this->create($param);
                    if ($result->code) {
                        $return['success'] = true;
                        $return['data'] = $result;
                    }
                } catch (Throwable $e) {
                    report($e);
                }

            }
        }
        return $return;
    }

    public function changeGamePass($game_code, $id, $data, $debug = false): array
    {
        $return['success'] = false;

        $games = $this->gameRepository->findOneByField('code', $game_code);
        $game_id = preg_replace('/\d/', '', $games->id);
        $game = ucfirst($game_id);
        if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {
            $return = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->changePass($data);
            if ($return['success'] === true) {

                $game_user = $this->findOrFail($id);
                $game_user->user_pass = $data['user_pass'];
                $game_user->save();


            }
        }

        return $return;
    }

    public function UserDeposit($game_code, $user_name, $total, $update = true, $debug = false): array
    {
        $return['success'] = false;

        $games = $this->gameRepository->findOneByField('code', $game_code);
        $game_id = preg_replace('/\d/', '', $games->id);
        $game = ucfirst($game_id);

        if ($debug) {
            return app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->deposit($user_name, $total);

        }

        $user = $this->findOneWhere(['user_name' => $user_name, 'game_code' => $game_code]);
        if (!is_null($games) && !is_null($user)) {
            if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {
                $return = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->deposit($user_name, $total);
                if ($update) {
                    if ($return['success'] === true) {

                        $user->balance = $return['after'];
                        $user->save();

                    }
                }
            }
        }

        return $return;
    }

    public function UserWithdraw($game_code, $user_name, $total, $update = true, $debug = false): array
    {
        $return['success'] = false;

        $games = $this->gameRepository->findOneByField('code', $game_code);
        $game_id = preg_replace('/\d/', '', $games->id);
        $game = ucfirst($game_id);

        if ($debug) {
            return app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->withdraw($user_name, $total);

        }

        $user = $this->findOneWhere(['user_name' => $user_name, 'game_code' => $game_code]);
        if (!is_null($games) && !is_null($user)) {
            if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {

                $return = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->withdraw($user_name, $total);
                if ($update) {
                    if ($return['success'] === true) {

                        $user->balance = $return['after'];
                        $user->save();

                    }
                }
            }
        }
        return $return;
    }

    public function viewBalance($game_code, $user_name, $update = true, $debug = false): int
    {
        $score = 0;

        $games = $this->gameRepository->findOneByField('code', $game_code);
        $game_id = preg_replace('/\d/', '', $games->id);
        $game = ucfirst($game_id);
        $user = $this->findOneWhere(['user_name' => $user_name, 'game_code' => $game_code]);
        if (!is_null($games) && !is_null($user)) {
            if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game . 'Repository.php'))) {
                $score = app('Gametech\Game\Repositories\Games\\' . $game . 'Repository')->viewBalance($user->username);
                if ($update) {
                    try {

                        $this->update(['balance' => $score], $user->code);

                    } catch (Exception $e) {

                        $score = 0;
                    }
                }

            }
        }
        return $score;
    }

    public function checkBalance($game_id, $user_name, $debug = false): array
    {
        $result['success'] = false;
        $result['msg'] = 'เกมดังกล่าว ยังไม่พร้อมให้บริการในขณะนี้';
        $game_id = preg_replace('/\d/', '', $game_id);
        $game_id = ucfirst($game_id);
        if (is_file(base_path('packages/Gametech/Game/src/Repositories/Games/' . $game_id . 'Repository.php'))) {
            return app('Gametech\Game\Repositories\Games\\' . $game_id . 'Repository', ['method' => $this->gameMethod, 'debug' => $debug])->viewBalance($user_name);

        }

        return $result;
    }

}
