<?php

namespace Gametech\Wallet\Http\Controllers;



use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameUserFreeRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Prettus\Validator\Exceptions\ValidatorException;


class ProfileController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $gameRepository;

    protected $memberRepository;

    protected $gameUserRepository;

    protected $gameUserFreeRepository;


    /**
     * Create a new Repository instance.
     *
     * @param MemberRepository $memberRepo
     * @param GameRepository $gameRepo
     * @param GameUserRepository $gameUserRepo
     * @param GameUserFreeRepository $gameUserFreeRepo
     */
    public function __construct
    (
        MemberRepository $memberRepo,
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

        $this->memberRepository = $memberRepo;

    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function index()
    {
        $games = $this->loadGame();
        $games = $games->map(function ($items){
            $item = (object)$items;
            return [
                'code' => $item->code,
                'name' => $item->name,
                'image' => Storage::url('game_img/'.$item->filepic),
                'balance' => $item->game_user['balance'],
                'user_code' => $item->game_user['code']
            ];

        });

        $gamesfree = $this->loadGameFree();
        $gamesfree = $gamesfree->map(function ($items){
            $item = (object)$items;
            return [
                'code' => $item->code,
                'name' => $item->name,
                'image' => Storage::url('game_img/'.$item->filepic),
                'balance' => $item->game_user_free['balance'],
                'user_code' => $item->game_user_free['code']
            ];

        });


        $profile = $this->user()->load('bank');

        return view($this->_config['view'], compact('profile','games','gamesfree'));
    }

    public function loadGame(): Collection
    {
        return collect($this->gameRepository->getGameUserById($this->id(),false)->toArray())->whereNotNull('game_user');

    }

    public function loadGameFree(): Collection
    {
        return collect($this->gameRepository->getGameUserFreeById($this->id(),false)->toArray())->whereNotNull('game_user_free');

    }

    public function changepass(Request $request): JsonResponse
    {
        $request->validate([
            'old_password' => 'required|min:6|password:customer',
            'password' => 'required|min:6',
            'password_confirmation' => 'min:6|same:password',
        ]);

        $mydata = $request->all();



        $data['user_pass'] = $mydata['password'];
        $data['password'] = Hash::make($mydata['password']);

        try {
            $this->memberRepository->update($data, $this->id());
        } catch (ValidatorException $e) {
        }

        return $this->sendSuccess('เปลี่ยนรหัสผ่านเรียบร้อย');


    }

    public function resetgamepass(Request $request)
    {
        $games = $this->loadGame();
        $games = collect($games)->map(function ($items) {

            return (object)$items;

        });


        $user = collect($this->user()->toArray());
        $user_pass = "Bb" . rand(100000, 999999);
        $game_err = [];
        foreach ($games as $i => $item) {


            $result = $this->gameUserRepository->changeGamePass($item->code, $item->game_user['code'],[
                'user_pass' => $user_pass,
                'user_name' => $item->game_user['user_name'],
                'name'      => $user['name'],
                'firstname'      => $user['firstname'],
                'lastname'      => $user['lastname'],
                'gender'      => $user['gender'],
                'birth_day'      => $user['birth_day'],
                'date_regis'      => $user['date_regis'],
            ]);

            if($result['success'] !== true){
                $game_err[] = $item->name;
            }

        }

        if (empty($game_err)) {
            $return['success'] = true;
            $msg = 'เกมทั้งหมด ได้ทำการเปลี่ยนรหัสผ่านแล้ว';
        }else{
            $return['success'] = true;
            $msg = 'เกมบางรายการ ได้ทำการเปลี่ยนรหัสผ่านแล้ว พบข้อผิดพลาดบางประการของเกม '.implode(", ", $game_err);
        }

        return $this->sendSuccess($msg);
    }

    public function resetgamefreepass(Request $request)
    {
        $games = $this->loadGameFree();
        $games = collect($games)->map(function ($items) {

            return (object)$items;

        });


        $user = collect($this->user()->toArray());
        $user_pass = "Cb" . rand(100000, 999999);
        $game_err = [];
        foreach ($games as $i => $item) {


            $result = $this->gameUserFreeRepository->changeGamePass($item->code, $item->game_user_free['code'],[
                'user_pass' => $user_pass,
                'user_name' => $item->game_user_free['user_name'],
                'name'      => $user['name'],
                'firstname'      => $user['firstname'],
                'lastname'      => $user['lastname'],
                'gender'      => $user['gender'],
                'birth_day'      => $user['birth_day'],
                'date_regis'      => $user['date_regis'],
            ]);

            if($result['success'] !== true){
                $game_err[] = $item->name;
            }

        }

        if (empty($game_err)) {
            $return['success'] = true;
            $msg = 'เกมทั้งหมด ได้ทำการเปลี่ยนรหัสผ่านแล้ว';
        }else{
            $return['success'] = true;
            $msg = 'เกมบางรายการ ได้ทำการเปลี่ยนรหัสผ่านแล้ว พบข้อผิดพลาดบางประการของเกม '.implode(", ", $game_err);
        }

        return $this->sendSuccess($msg);
    }

    public function view(Request $request)
    {
        $id = $request->input('id');
        $result = $this->gameUserRepository->getOneUser($this->id() ,$id , false);

        $result = collect($result['data']->toArray())->only(['user_name','user_pass','game']);
//        $result = $this->gameUserRepository->with('game')->findOneWhere(['game_code' => $id , 'member_code' => $this->id()] , ['user_name as user','user_pass as pass']);

        return $this->sendResponseNew($result,'complete');
    }

    public function viewfree(Request $request)
    {
        $id = $request->input('id');
        $result = $this->gameUserFreeRepository->getOneUser($this->id() ,$id , false);

        $result = collect($result['data']->toArray())->only(['user_name','user_pass','game']);

        return $this->sendResponseNew($result,'complete');
    }


}
