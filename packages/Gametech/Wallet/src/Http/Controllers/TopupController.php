<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankRepository;
use Gametech\Payment\Repositories\BankRuleRepository;
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

    protected $bankRuleRepository;


    /**
     * Create a new Repository instance.
     *
     * @param BankRepository $bankRepo
     * @param MemberRepository $memberRepo
     */
    public function __construct
    (
        BankRepository $bankRepo,
        MemberRepository $memberRepo,
        BankRuleRepository $bankRuleRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->bankRepository = $bankRepo;

        $this->memberRepository = $memberRepo;

        $this->bankRuleRepository = $bankRuleRepo;
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



        $bankss = $bankss->transform(function ($item, $key) {
            $item['filepic'] = Storage::url('bank_img/' . $item['filepic']);
            return $item;
        });

//        dd($bankss);



        $rules = collect($this->bankRuleRepository->all())->toArray();
        $pass = false;
        if(count($rules) > 0){
            foreach($rules as $i => $item){


            if($pass)break;
            if($item['types'] == 'IF'){
                if($profile->bank->code == $item['bank_code']){
                    if($item['method'] == 'CAN'){
                        $bcode = explode(',',$item['bank_number']);
                        if(count($bcode) > 1){
                            $banks = $bankss->whereIn('code',$bcode)->all();
                        }else{
                            $banks = $bankss->whereIn('code',[$item['bank_number']])->all();
                        }


                    }else{
                        $bcode = explode(',',$item['bank_number']);
                        if(count($bcode) > 1){
                            $banks = $bankss->whereNotIn('code',$bcode)->all();
                        }else{
                            $banks = $bankss->whereNotIn('code',[$item['bank_number']])->all();
                        }

                    }
                    $pass = true;
                }
            }else{

                if($profile->bank->code != $item['bank_code']){
                    if($item['method'] == 'CAN'){
                        $bcode = explode(',',$item['bank_number']);
                        if(count($bcode) > 1){
                            $banks = $bankss->whereIn('code',$bcode)->all();
                        }else{
                            $banks = $bankss->whereIn('code',[$item['bank_number']])->all();
                        }

                    }else{
                        $bcode = explode(',',$item['bank_number']);
                        if(count($bcode) > 1){
                            $banks = $bankss->whereNotIn('code',$bcode)->all();
                        }else{
                            $banks = $bankss->whereNotIn('code',[$item['bank_number']])->all();
                        }

                    }
                    $pass = true;
                }



            }
        }
        }else{
            $banks = $bankss;
        }


        return view($this->_config['view'], compact('banks', 'profile'));
    }


}
