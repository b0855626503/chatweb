<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Game\Repositories\GameRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;


class CreditController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $gameUserFreeRepository;

    protected $memberRepository;

    protected $gameRepository;

    protected $withdrawFreeRepository;

    /**
     * Create a new Repository instance.
     * @param GameRepository $gameRepo
     */
    public function __construct
    (
        GameRepository $gameRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->gameRepository = $gameRepo;

    }

    public function index()
    {
        $games = $this->loadGame();

        $games = $games->mapToGroups(function ($items, $key){
            $item = (object)$items;

            return [strtolower($item->game_type) => [
                'code' => $item->code,
                'name' => $item->name,
                'image' =>  Storage::url('game_img/'.$item->filepic),
                'balance' => (!is_null($item->game_user_free) ? $item->game_user_free['balance'] : 0),
                'user_code' => (!is_null($item->game_user_free) ? $item->game_user_free['code'] : '')
            ]];

        });


        return view($this->_config['view'],compact('games'));
    }

    public function loadGame(): Collection
    {
        return collect($this->gameRepository->getGameUserFreeById($this->id(), false)->toArray());

    }


}
