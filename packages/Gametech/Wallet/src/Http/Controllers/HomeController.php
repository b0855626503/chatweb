<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameUserFreeRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;


class HomeController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $gameRepository;

    protected $gameUserRepository;

    protected $gameUserFreeRepository;

    /**
     * Create a new Repository instance
     * @param GameRepository $gameRepo
     * @param GameUserRepository $gameUserRepo
     * @param GameUserFreeRepository $gameUserFreeRepo
     */
    public function __construct
    (

        GameRepository $gameRepo,
        GameUserRepository $gameUserRepo,
        GameUserFreeRepository $gameUserFreeRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->gameRepository = $gameRepo;

        $this->gameUserRepository = $gameUserRepo;

        $this->gameUserFreeRepository = $gameUserFreeRepo;
    }

    public function index()
    {

        $games = $this->loadGame();
        $games = $games->mapToGroups(function ($items, $key) {
            $item = (object)$items;
            return [strtolower($item->game_type) => [
                'code' => $item->code,
                'name' => $item->name,
                'image' => Storage::url('game_img/' . $item->filepic),
                'balance' => (!is_null($item->game_user) ? $item->game_user['balance'] : 0),
                'user_code' => (!is_null($item->game_user) ? $item->game_user['code'] : '')
            ]];

        });

        $profile = $this->user();

        return view($this->_config['view'], compact('games', 'profile'));

    }


    public function loadGame(): Collection
    {

        return collect($this->gameRepository->getGameUserById($this->id(), false)->toArray());

    }

    public function loadProfile(): JsonResponse
    {
        $config = collect(core()->getConfigData());

        $configs = $config->map(function ($value, $key) {
            if ($key == 'point_open') {
                return ($value === 'Y' ? true : false);
            }
            if ($key == 'diamond_open') {
                return ($value === 'Y' ? true : false);
            }
        });

        $configs->only('point_open', 'diamond_open');

        $confignew['point'] = $configs['point_open'];
        $confignew['diamond'] = $configs['diamond_open'];

//dd($confignew);
        $result['profile'] = $this->user()->only('balance', 'point_deposit', 'diamond', 'balance_free');
        $result['system'] = $confignew;
        return $this->sendResponseNew($result, 'complete');
    }

    public function loadGameID($game)
    {

        $item = collect($this->gameUserRepository->getOneUser($this->id(), $game, true))->toArray();
        if ($item['success'] === true) {


            $response['connect'] = $item['connect'];
            $response['user_code'] = $item['data']['code'];
            $response['code'] = $item['data']['game']['code'];
            $response['name'] = $item['data']['game']['name'];
            $response['balance'] = $item['data']['balance'];
            $response['image'] = Storage::url('game_img/' . $item['data']['game']['filepic']);

        } else {
            $games = $this->gameRepository->find($game);

            $response['connect'] = false;
            $response['user_code'] = 0;
            $response['code'] = $game;
            $response['name'] = $games->name;
            $response['balance'] = 0;
            $response['image'] = Storage::url('game_img/' . $games->filepic);

        }


        return $this->sendResponseNew($response, 'complete');
    }

    public function loadGameFreeID($game)
    {

        $item = collect($this->gameUserFreeRepository->getOneUser($this->id(), $game, true))->toArray();
        if ($item['success'] === true) {
            $response['user_code'] = $item['data']['code'];
            $response['code'] = $item['data']['game']['code'];
            $response['name'] = $item['data']['game']['name'];
            $response['balance'] = $item['data']['balance'];
            $response['image'] = Storage::url('game_img/' . $item['data']['game']['filepic']);

        } else {
            $games = $this->gameRepository->find($game);

            $response['user_code'] = 0;
            $response['code'] = $game;
            $response['name'] = $games->name;
            $response['balance'] = 0;
            $response['image'] = Storage::url('game_img/' . $games->filepic);

        }
        return $this->sendResponseNew($response, 'complete');
    }

    public function create(Request $request): JsonResponse
    {
        $game = $request->input('id');
        $user = $this->gameUserRepository->findOneWhere(['game_code' => $game, 'member_code' => $this->id(),'enable' => 'Y']);
        if (!$user) {
            $response = $this->gameUserRepository->addGameUser($game, $this->id(), collect($this->user())->toArray());
            if ($response['success'] === true) {
                return $this->sendResponseNew($response['data'], 'ระบบได้ทำการสร้างบัญชีเกม เรียบร้อยแล้ว');
            } else {
                return $this->sendError($response['msg'], 200);
            }
        } else {
            return $this->sendError('ไม่สามารถดำเนินการได้ คุณมีบัญชีเกมนี้ในระบบแล้ว', 200);
        }
    }

    public function createfree(Request $request): JsonResponse
    {
        $game = $request->input('id');
        $user = $this->gameUserFreeRepository->findOneWhere(['game_code' => $game, 'member_code' => $this->id(),'enable' => 'Y']);
        if (!$user) {
            $response = $this->gameUserFreeRepository->addGameUser($game, $this->id(), collect($this->user())->toArray());
            if ($response['success'] === true) {
                return $this->sendResponseNew($response['data'], 'ระบบได้ทำการสร้างบัญชีเกม เรียบร้อยแล้ว');
            } else {
                return $this->sendError($response['msg'], 200);
            }
        } else {
            return $this->sendError('ไม่สามารถดำเนินการได้ คุณมีบัญชีเกมนี้ในระบบแล้ว', 200);
        }
    }


}
