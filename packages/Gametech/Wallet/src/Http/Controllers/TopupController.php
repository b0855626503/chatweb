<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Support\Facades\Storage;


class TopupController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $bankRepository;

    protected $memberRepository;


    /**
     * Create a new Repository instance.
     *
     * @param BankRepository $bankRepo
     * @param MemberRepository $memberRepo
     */
    public function __construct
    (
        BankRepository $bankRepo,
        MemberRepository $memberRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->bankRepository = $bankRepo;

        $this->memberRepository = $memberRepo;
    }

    public function indextest()
    {
        $banks = collect($this->bankRepository->getBankInAccount()->toArray());

        $banks = $banks->transform(function ($item, $key) {
            $item['filepic'] = Storage::url('bank_img/' . $item['filepic']);
            return $item;
        });

        $profile = $this->user()->load('bank');

        return view($this->_config['view'], compact('banks', 'profile'));
    }

    public function index()
    {
        $profile = $this->user()->load('bank');

        $bankss = collect($this->bankRepository->getBankInAccountAll()->toArray());


        $banks = $bankss->transform(function ($item, $key) {
            $item['filepic'] = Storage::url('bank_img/' . $item['filepic']);
            return $item;
        });


        return view($this->_config['view'], compact('banks', 'profile'));
    }


}
