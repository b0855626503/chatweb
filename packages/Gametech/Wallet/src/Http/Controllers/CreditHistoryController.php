<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CreditHistoryController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $bankRepository;

    protected $memberRepository;

    protected $sum;

    protected $sum_ic;

    protected $sum_cashback;


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

        $this->sum = 0;

        $this->sum_ic = 0;
        $this->sum_cashback = 0;
    }

    public function index()
    {
//       $banks = $this->bankRepository->getBankAccount();
//
//       $profile = auth()->guard('customer')->user()->with('bank')->first();

        $banks[] = ['method' => 'transfer' , 'name' => 'การโยก'];
        $banks[] = ['method' => 'withdraw' , 'name' => 'การถอน'];
        $banks[] = ['method' => 'cashback' , 'name' => 'Cashback'];
        $banks[] = ['method' => 'memberic' , 'name' => 'IC'];

        $banks = collect($banks);

        return view($this->_config['view'], compact('banks'));
    }

    public function store()
    {
        $result['success'] = true;
        $date_start = request()->input('date_start');
        $date_stop = request()->input('date_stop');
        $id = request()->input('id');

        switch ($id){
            case 'cashback':
                $result['data'] = $this->loadCashback($date_start,$date_stop);
                $result['sum'] = $this->sum;
                break;
            case 'withdraw':
                $result['data'] = $this->loadWithdraw($date_start,$date_stop);
                break;
            case 'transfer':
                $result['data'] = $this->loadBill($date_start,$date_stop);
                break;
            case 'memberic':
                $result['data'] = $this->loadIC($date_start,$date_stop);
                $result['sum_ic'] = core()->currency($this->sum_ic);
                $result['sum_cashback'] = core()->currency($this->sum_cashback);
                break;
            default:
                $result['data'] = '';
        }

        return json_encode($result);
    }

    public function loadWithdraw($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdrawFree($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            $status = [ '0' => 'รอโอนเงิน' , '1' => 'โอนเงินสำเร็จ' , '2' => 'ยกเลิก'];
            $color = [ '0' => 'badge-info' , '1' => 'badge-success' , '2' => 'badge-primary'];

            return [
                'code' => $item->code,
                'id' => '#WD'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->oldcredit,
                'credit_after' => $item->aftercredit,
                'status' => $color[$item->status],
                'status_display' => $status[$item->status]
            ];

        });

        return $responses;

    }

    public function loadBill($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadBillFree($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'code' => $item->code,
                'id' => '#BL'.Str::of($item->code)->padLeft(8,0),
                'promotion_name' => (!is_null($item->promotion) ? $item->promotion : 'ไม่มี'),
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'balance_before' => $item->balance_before,
                'balance_after' => $item->balance_after,
                'credit' => $item->credit,
                'credit_bonus' => $item->credit_bonus,
                'credit_balance' => $item->credit_balance,
                'credit_before' => $item->credit_before,
                'credit_after' => $item->credit_after,
                'game_name' => $item->game['name'],
                'filepic' => $item->transfer_type == 1 ? Storage::url('game_img/'.$item->game['filepic']) : Storage::url('game_img/wallet.png'),
                'transfer' => $item->transfer_type == 1 ? 'Cashback -> Game (โยกเข้าเกม)' : 'Cashback <- Game (โยกออกเกม)',
                'status' => $item->transfer_type == 1 ? 'text-success' : 'text-danger',
            ];

        });

        return $responses;

    }

    public function loadCashback($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadCashback($this->id(),$date_start,$date_stop)->toArray());
//dd($responses);
        $responses = $responses->map(function ($items){

            $item = (object)$items;

            if($item->credit_type == 'D'){
                $this->sum += $item->credit_amount;
                $item->status_display = 'โอนเข้า';
                $item->status = 'badge-success';
            }elseif($item->credit_type == 'W'){
                $this->sum -= $item->credit_amount;
                $item->status_display = 'โอนออก';
                $item->status = 'badge-danger';
            }

            return [
                'code' => $item->code,
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->credit_amount,
                'credit_before' => $item->credit_before,
                'credit_after' => $item->credit_balance,
                'status_display' => $item->status_display,
                'status' => $item->status,
            ];

        });



        return $responses;

    }

    public function loadIC($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadIC($this->id(),$date_start,$date_stop)->toArray())->whereNotNull('down');

        $responses = $responses->map(function ($items){

            $item = (object)$items;
//            dd($item);
            $this->sum_ic += $item->ic;
            $this->sum_cashback += $item->cashback;

            return [
                'code' => $item->code,
                'id' => '#CB'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_cashback,'d/m/y H:i'),
                'cashback' => $item->cashback,
                'balance' => $item->balance,
                'ic' => $item->ic,
                'downline' => $item->down['name'],
                'downline_code' => $item->down['code'],
//                'status_display' => $item->status_display,
            ];

        });



        return $responses;

    }


}
