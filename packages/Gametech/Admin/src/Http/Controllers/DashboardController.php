<?php

namespace Gametech\Admin\Http\Controllers;


use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class DashboardController extends AppBaseController
{
    protected $_config;

    /**
     * string object
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $startDate;

    /**
     * string object
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $lastStartDate;

    /**
     * string object
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $endDate;

    /**
     * string object
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $lastEndDate;

    public function __construct()
    {
        $this->_config = request('_config');

        $this->middleware('admin');
    }


    public function index()
    {
        $this->setStartEndDate();

        return view($this->_config['view'])->with(['startDate' => $this->startDate, 'endDate' => $this->endDate]);
    }

    public function loadCnt()
    {
        $startdate = now()->toDateString() . ' 00:00:00';
        $enddate = now()->toDateString() . ' 23:59:59';
        $today = now()->toDateString();

        $bank_in_today = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->income()->active()->waiting()
            ->whereDate('date_create', $today)
            ->whereIn('autocheck', ['N', 'W'])
            ->count();
        $bank_in = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->income()->active()->waiting()
            ->whereIn('autocheck', ['N', 'W'])
            ->count();

        $bank_out = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->profit()->active()->waiting()
            ->where('autocheck', 'N')
            ->whereBetween('date_create', array($startdate, $enddate))
            ->count();

        $withdraw = app('Gametech\Payment\Repositories\WithdrawRepository')
            ->active()->waiting()
            ->count();
        $withdraw_free = app('Gametech\Payment\Repositories\WithdrawFreeRepository')
            ->active()->waiting()
            ->count();

        $payment_waiting = app('Gametech\Payment\Repositories\PaymentWaitingRepository')
            ->whereDate('date_create', '>', '2021-04-05')
            ->active()->waiting()
            ->count();


        $announce = [
            'content' => '',
            'updated_at' => now()->toDateTimeString()
        ];

        $announce_new = 'N';

        $response = Http::get('https://announce.168csn.com/api/announce');

        if ($response->successful()) {
            $response = $response->json();
//            dd($response);
            $announce = $response['data'];
        }

//        dd($announce);

        if (!Cache::has($this->id() . 'announce_start')) {
            Cache::add($this->id() . 'announce_stop', $announce['updated_at']);
        }
        if (!Cache::has($this->id() . 'announce_stop')) {
            Cache::add($this->id() . 'announce_stop', $announce['updated_at']);
        } else {
            Cache::put($this->id() . 'announce_stop', $announce['updated_at']);
        }

        $start = Cache::get($this->id() . 'announce_start');
        $stop = Cache::get($this->id() . 'announce_stop');
        if ($start != $stop) {
            $announce_new = 'Y';
            Cache::put($this->id() . 'announce_start', $stop);
        }




        $result['bank_in_today'] = $bank_in_today;
        $result['bank_in'] = $bank_in;
        $result['bank_out'] = $bank_out;
        $result['withdraw'] = $withdraw;
        $result['withdraw_free'] = $withdraw_free;
        $result['payment_waiting'] = $payment_waiting;
        $result['announce'] = $announce['content'];
        $result['announce_new'] = $announce_new;

        return $this->sendResponseNew($result, 'Complete');

    }

    public function loadSum(Request $request)
    {
        $startdate = now()->toDateString();
//        $startdate = '2021-02-10';
        $data = 0;
        $method = $request->input('method');
        switch ($method) {
            case  'setdeposit':
                $data = app('Gametech\Member\Repositories\MemberCreditLogRepository')->active()->where('kind', 'SETWALLET')->where('credit_type', 'D')->whereDate('date_create', $startdate)->sum('amount');
                $data = core()->currency($data);
                break;
            case  'setwithdraw':
                $data = app('Gametech\Member\Repositories\MemberCreditLogRepository')->active()->where('kind', 'SETWALLET')->where('credit_type', 'W')->whereDate('date_create', $startdate)->sum('amount');
                $data = core()->currency($data);
                break;
            case  'deposit':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->complete()->whereDate('date_create', $startdate)->sum('value');
                $data = core()->currency($data);
                break;
            case  'deposit_wait':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->waiting()->where('autocheck','Y')->whereDate('date_create', $startdate)->sum('value');
                $data = core()->currency($data);
                break;
            case  'withdraw':
                $data = app('Gametech\Payment\Repositories\WithdrawRepository')->active()->complete()->whereRaw(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') = ?"), [$startdate])->sum('amount');
                $data = core()->currency($data);
                break;
            case  'bonus':
                $data1 = app('Gametech\Payment\Repositories\PaymentPromotionRepository')->active()->aff()->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') = ?"), [$startdate])->sum('credit_bonus');
                $data2 = app('Gametech\Payment\Repositories\BillRepository')->active()->getpro()->where('transfer_type', 1)->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') = ?"), [$startdate])->sum('credit_bonus');
                $data = core()->currency($data1 + $data2);
                break;
            case  'balance':
                $data1 = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->whereIn('status', [0, 1])->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') = ?"), [$startdate])->sum('value');
                $data2 = app('Gametech\Payment\Repositories\WithdrawRepository')->active()->complete()->whereRaw(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') = ?"), [$startdate])->sum('amount');
                $data3 = app('Gametech\Payment\Repositories\PaymentPromotionRepository')->active()->aff()->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') = ?"), [$startdate])->sum('credit_bonus');
                $data4 = app('Gametech\Payment\Repositories\BillRepository')->active()->getpro()->where('transfer_type', 1)->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') = ?"), [$startdate])->sum('credit_bonus');

                $data = core()->currency($data1 - $data2);
                break;
        }

        $result['sum'] = $data;
        return $this->sendResponseNew($result, 'Complete');
    }

    public function loadSumAll(Request $request)
    {
//        $startdate = '2021-02-04';
//        $enddate = '2021-02-10';
        $startdate = now()->subDays(6)->toDateString();
        $enddate = now()->toDateString();

        $date_arr = core()->generateDateRange($startdate, $enddate);
//        dd($date_arr);

        $method = $request->input('method');
        switch ($method) {
            case  'income':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()
                    ->complete()
                    ->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('Date(date_create)'))
                    ->select(DB::raw('SUM(value) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();


                $datas = collect($data->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();


                $data2 = app('Gametech\Payment\Repositories\WithdrawRepository')->active()->complete()
                    ->whereRaw(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('Date(date_approve)'))
                    ->select(DB::raw('SUM(amount) as value'), DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') as date"))->get();

                $datas2 = collect($data2->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();

                $data3 = app('Gametech\Payment\Repositories\PaymentPromotionRepository')->active()->aff()
                    ->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('Date(date_create)'))
                    ->select(DB::raw('SUM(credit_bonus) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();

                $datas3 = collect($data3->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();

                $data4 = app('Gametech\Payment\Repositories\BillRepository')->active()->getpro()
                    ->where('transfer_type', 1)
                    ->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('Date(date_create)'))
                    ->select(DB::raw('SUM(credit_bonus) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();

                $datas4 = collect($data4->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();

                foreach ($date_arr as $i => $dt) {
                    $x1 = (empty($datas[$dt]) ? 0 : $datas[$dt][0]);
                    $x2 = (empty($datas2[$dt]) ? 0 : $datas2[$dt][0]);
                    $x3 = (empty($datas3[$dt]) ? 0 : $datas3[$dt][0]);
                    $x4 = (empty($datas4[$dt]) ? 0 : $datas4[$dt][0]);
                    $a = intval($x1);
                    $b = intval($x2);
                    $c = intval($x3);
                    $d = intval($x4);
                    $balance = ($a - $b);

                    $result['label'][] = core()->Date($dt, 'd M');
                    $result['line_deposit'][] = $a;
                    $result['line_withdraw'][] = $b;
                    $result['line_bonus'][] = ($c + $d);
                    $result['line_balance'][] = $balance;
                }

                break;

            case 'topup':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()
                    ->whereIn('status', [0, 1])
                    ->whereRaw(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('Date(date_create)'))
                    ->select(DB::raw('SUM(value) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();


                $datas = collect($data->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();

                foreach ($date_arr as $i => $dt) {
                    $x1 = (empty($datas[$dt]) ? 0 : $datas[$dt][0]);

                    $a = intval($x1);


                    $result['label'][] = core()->Date($dt, 'd M');
                    $result['bar'][] = $a;

                }

                break;

            case 'register':
                $data = app('Gametech\Member\Repositories\MemberRepository')->active()
                    ->whereRaw(DB::raw("date_regis between ? and ? "), [$startdate, $enddate])
                    ->groupBy(DB::raw('date_regis'))
                    ->select(DB::raw('COUNT(*) as value'), DB::raw("date_regis as date"))->get();


                $datas = collect($data->toArray())->mapToGroups(function ($item, $key) {
                    return [$item['date'] => $item['value']];
                })->toArray();

                foreach ($date_arr as $i => $dt) {
                    $x1 = (empty($datas[$dt]) ? 0 : $datas[$dt][0]);

                    $a = intval($x1);


                    $result['label'][] = core()->Date($dt, 'd M');
                    $result['bar'][] = $a;

                }

                break;


        }


        return $this->sendResponseNew($result, 'Complete');
    }

    public function loadBank(Request $request)
    {
        $result['list'] = [];
        $method = $request->input('method');
        switch ($method) {
            case 'bankin':
                $responses = collect(app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInAll()->toArray());

                $response = $responses->map(function ($items) {

                    return [
                        'date_update' => core()->formatDate($items['checktime'], 'd/m/y H:i:s'),
                        'bank' => core()->displayBank($items['bank']['shortcode'], $items['bank']['filepic']),
                        'acc_no' => $items['acc_no'],
                        'balance' => core()->currency($items['balance'])
                    ];

                });


                $result['list'] = $response;

                break;
            case 'bankout':

                $responses = collect(app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutAll()->toArray());

                $response = $responses->map(function ($items) {

                    return [
                        'date_update' => core()->formatDate($items['checktime'], 'd/m/y H:i:s'),
                        'bank' => core()->displayBank($items['bank']['shortcode'], $items['bank']['filepic']),
                        'acc_no' => $items['acc_no'],
                        'balance' => core()->currency($items['balance'])
                    ];

                });


                $result['list'] = $response;

                break;
        }

        return $this->sendResponseNew($result, 'complete');
    }

    public function setStartEndDate()
    {
        $this->startDate = request()->get('start')
            ? Carbon::createFromTimeString(request()->get('start') . " 00:00:01")
            : Carbon::createFromTimeString(Carbon::now()->subDays(30)->format('Y-m-d') . " 00:00:01");

        $this->endDate = request()->get('end')
            ? Carbon::createFromTimeString(request()->get('end') . " 23:59:59")
            : Carbon::now();

        if ($this->endDate > Carbon::now()) {
            $this->endDate = Carbon::now();
        }

        $this->lastStartDate = clone $this->startDate;
        $this->lastEndDate = clone $this->startDate;

        $this->lastStartDate->subDays($this->startDate->diffInDays($this->endDate));
        // $this->lastEndDate->subDays($this->lastStartDate->diffInDays($this->lastEndDate));
    }

    public function getAnnounce()
    {
        $announce = [
            'content' => '',
            'updated_at' => now()->toDateTimeString()
        ];

        $announce_new = 'N';
        $result['content'] = '';
        $result['new'] = $announce_new;

        $response = Http::get('https://announce.168csn.com/api/announce');

        if ($response->successful()) {
            $response = $response->json();
            $announce = $response['data'];
        }

        if (!Cache::has($this->id() . 'announce_start')) {
            Cache::add($this->id() . 'announce_stop', $announce['updated_at']);
        }
        if (!Cache::has($this->id() . 'announce_stop')) {
            Cache::add($this->id() . 'announce_stop', $announce['updated_at']);
        } else {
            Cache::put($this->id() . 'announce_stop', $announce['updated_at']);
        }

        $start = Cache::get($this->id() . 'announce_start');
        $stop = Cache::get($this->id() . 'announce_stop');
        if ($start != $stop) {
            $announce_new = 'Y';
            Cache::put($this->id() . 'announce_start', $stop);
        }


        $result['content'] = $announce['content'];
        $result['new'] = $announce_new;
        return $result;
    }


}
