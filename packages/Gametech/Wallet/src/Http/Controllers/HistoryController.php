<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class HistoryController extends AppBaseController
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

    public function index()
    {
//       $banks = $this->bankRepository->getBankAccount();
//
//       $profile = auth()->guard('customer')->user()->with('bank')->first();

        $banks[] = ['method' => 'deposit' , 'name' => 'การเติม'];
        $banks[] = ['method' => 'withdraw' , 'name' => 'การถอน'];
        $banks[] = ['method' => 'transfer' , 'name' => 'การโยก'];

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
            case 'deposit':
                $result['data'] = $this->loadDeposit($this->id(),$date_start,$date_stop);
                break;
            case 'withdraw':
                $result['data'] = $this->loadWithdraw($this->id(),$date_start,$date_stop);
                break;
            case 'transfer':
                $result['data'] = $this->loadBill($this->id(),$date_start,$date_stop);
                break;
            default:
                $result['data'] = '';
        }

        return json_encode($result);

    }

    public function loadDeposit($id,$date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadDeposit($id,$date_start,$date_stop)->toArray());
        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'id' => '#DP'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_create,'d/m/Y H:i'),
                'amount' => $item->value,
                'credit_bonus' => $item->pro_amount,
                'credit_before' => $item->before_credit,
                'credit_after' => $item->after_credit
            ];

        });

        return $responses;

    }

    public function loadWithdraw($id,$date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdraw($id,$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            $status = [ '0' => 'รอโอนเงิน' , '1' => 'โอนเงินสำเร็จ' , '2' => 'ยกเลิก'];
            $color = [ '0' => 'badge-info' , '1' => 'badge-success' , '2' => 'badge-primary'];

            return [
                'code' => $item->code,
                'id' => '#WD'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_create,'d/m/Y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->oldcredit,
                'credit_after' => $item->aftercredit,
                'status' => $color[$item->status],
                'status_display' => $status[$item->status]
            ];

        });

        return $responses;

    }

    public function loadBill($id,$date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadBill($id,$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'code' => $item->code,
                'id' => '#BL'.Str::of($item->code)->padLeft(8,0),
                'promotion_name' => (!is_null($item->promotion) ? $item->promotion : 'ไม่มี'),
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
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
                'transfer' => $item->transfer_type == 1 ? 'Wallet -> Game (โยกเข้าเกม)' : 'Wallet <- Game (โยกออกเกม)',
                'status' => $item->transfer_type == 1 ? 'text-success' : 'text-danger',
            ];

        });

        return $responses;

    }


}
