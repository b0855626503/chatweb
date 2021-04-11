<?php

namespace Gametech\Wallet\Http\Controllers;

use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Support\Collection;

class ContributorController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $memberRepository;

    /**
     * Create a new Repository instance.
     * @param MemberRepository $memberRepo
     */
    public function __construct
    (
        MemberRepository $memberRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->memberRepository = $memberRepo;

    }

    public function index()
    {
        $profile = $this->memberRepository->getAff($this->id());
//        dd($profile);

        return view($this->_config['view'], compact('profile'));
    }

    public function store()
    {
        $result['success'] = true;
        $date_start = request()->input('date_start');
        $date_stop = request()->input('date_stop');



        $result['data'] = $this->loadDownline($date_start,$date_stop);

        return json_encode($result);

    }

    public function loadDownline($date_start=null,$date_stop=null): Collection
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadDownline($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            $sub = (object)$item->down;

            return [
                'code' => $sub->code,
                'date_regis' => core()->Date($sub->date_regis,'d/m/Y'),
                'amount' => $item->amount,
                'bonus' => $item->credit_bonus,
                'name' => $sub->name,

            ];

        });

        return $responses;

    }



}
