<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\WithdrawRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;


class WithdrawController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $memberRepository;

    protected $withdrawRepository;


    /**
     * Create a new Repository instance.
     *
     * @param MemberRepository $memberRepo
     * @param WithdrawRepository $withdrawRepo
     */
    public function __construct
    (
        MemberRepository $memberRepo,
        WithdrawRepository $withdrawRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->memberRepository = $memberRepo;

        $this->withdrawRepository = $withdrawRepo;
    }

    public function index()
    {
        $profile = $this->memberRepository->sumWithdraw($this->id(),now()->toDateString());
        return view($this->_config['view'], compact('profile'));
    }



    public function store(Request $request): RedirectResponse
    {
        $datenow = now();
        $today = $datenow->toDateString();

        $request->validate([
            'amount' => 'required|numeric'
        ]);

        $config = core()->getConfigData();

        $member = $this->user();
        $id = $this->id();

        if(is_null($member->bank)){
            session()->flash('error', 'พบข้อผิดพลาด ธนาคารที่ถูกระบุ ไม่ถูกต้อง');
            return redirect()->back();

        }

        $withdraw_today = $this->memberRepository->sumWithdraw($id,$today)->withdraw_amount_sum;
        $withdraw = (is_null($withdraw_today) ? 0 : $withdraw_today);


        $amount = floatval($request->input('amount'));
        $balance = $member['balance'];

        if ($amount < 1) {

            session()->flash('error', 'พบข้อผิดพลาด คุณป้อนจำนวนไม่ถูกต้อง');
            return redirect()->back();

        } elseif ($balance < $amount) {

            session()->flash('error', 'พบข้อผิดพลาด จำนวนเงินไม่เพียงพอ');
            return redirect()->back();

        } elseif ($amount < $config['minwithdraw']) {

            session()->flash('error', 'พบข้อผิดพลาด ไม่สามารถถอนต่ำกว่า '.core()->currency($config['minwithdraw']).' บาท');
            return redirect()->back();

        } elseif (($amount + $withdraw) > $config['maxwithdraw_day']) {

            session()->flash('error', 'พบข้อผิดพลาด ไม่สามารถถอนเกิน '.core()->currency($config['maxwithdraw_day']).' บาท / วัน');
            return redirect()->back();

        }  else {

            $response = $this->withdrawRepository->withdraw($id,$amount);

            if($response){
                session()->flash('success', 'คุณทำรายการแจ้งถอนเงิน สำเร็จแล้ว');
            }else{
                session()->flash('error', 'พบข้อผิดพลาด ไม่สามารถทำรายการได้');
            }
            return redirect()->back();

        }
    }






}
