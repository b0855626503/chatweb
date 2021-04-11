<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;

use Gametech\Payment\Repositories\WithdrawFreeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;


class CreditWithdrawController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $memberRepository;

    protected $withdrawFreeRepository;


    /**
     * Create a new Repository instance.
     *
     * @param MemberRepository $memberRepo
     * @param WithdrawFreeRepository $withdrawFreeRepo
     */
    public function __construct
    (
        MemberRepository $memberRepo,
        WithdrawFreeRepository $withdrawFreeRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->memberRepository = $memberRepo;

        $this->withdrawFreeRepository = $withdrawFreeRepo;
    }

    public function index()
    {
        $profile = $this->memberRepository->sumWithdrawFree($this->id());
        return view($this->_config['view'], compact('profile'));
    }



    public function store(Request $request): RedirectResponse
    {

        $request->validate([
            'amount' => 'required|numeric'
        ]);

        $config = core()->getConfigData();


        $member = $this->user();
        $id = $this->id();

        $withdraw_today = $this->memberRepository->sumWithdrawFree($id)->withdraw_free_amount_sum;
        $withdraw = (is_null($withdraw_today) ? 0 : $withdraw_today);


        $amount = floatval($request->input('amount'));
        $balance = $member['balance_free'];

        if ($amount < 1) {

            session()->flash('error', 'พบข้อผิดพลาด คุณป้อนจำนวนไม่ถูกต้อง');
            return redirect()->back();

        } elseif ($balance < $amount) {

            session()->flash('error', 'ไม่สามารถดำเนินการได้ จำนวนเงินไม่เพียงพอ');
            return redirect()->back();

        } elseif ($amount < $config['free_minwithdraw']) {

            session()->flash('error', 'ไม่สามารถดำเนินการได้ ยอดถอนขั้นต่ำ '.core()->currency($config['free_minwithdraw']).' บาท');
            return redirect()->back();

        } elseif (($amount + $withdraw) > $config['free_maxwithdraw']) {

            session()->flash('error', 'ไม่สามารถดำเนินการได้ ยอดถอนสูงสุด '.core()->currency($config['free_maxwithdraw']).' บาท');
            return redirect()->back();

        }  else {


            $response = $this->withdrawFreeRepository->withdraw($id,$amount);

            if($response){
                session()->flash('success', 'คุณทำรายการแจ้งถอนเงินแคชแบ๊ก สำเร็จแล้ว');
            }else{
                session()->flash('error', 'พบข้อผิดพลาด ไม่สามารถทำรายการได้');

            }
            return redirect()->back();

        }
    }






}
