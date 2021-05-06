<?php

namespace Gametech\Wallet\Http\Controllers;


use App\Http\Requests\Request;
use Gametech\Member\Repositories\MemberRepository;


class VerifyController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;


    private $memberRepository;


    /**
     * Create a new Repository instance.
     *

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
        return view($this->_config['view']);
    }

    public function update(Request $request)
    {
        $otp = $request->input('otp');

        $user = $this->user();

        if($user->otp == $otp && $user->confirm == 'N'){
            $user->confirm = 'Y';
            $user->save();
            session()->flash('success', 'ทำรายการสำเร็จ ขอบคุณสำหรับการยืนยันตน');
            return redirect()->route('customer.home.index');
        }else{
            session()->flash('error', 'รหัส OTP ไม่ถูกต้อง โปรดตรวจสอบ');
            return redirect()->back();
        }

    }


}
