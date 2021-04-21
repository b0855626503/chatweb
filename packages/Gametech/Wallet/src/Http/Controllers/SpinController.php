<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Core\Repositories\SpinRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BonusSpinRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;


class SpinController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $spinRepository;

    protected $memberRepository;

    protected $bonusSpinRepository;


    /**
     * Create a new Repository instance.
     *
     * @param SpinRepository $spinRepo
     * @param MemberRepository $memberRepo
     * @param BonusSpinRepository $bonusSpinRepo
     */
    public function __construct
    (
        SpinRepository $spinRepo,
        MemberRepository $memberRepo,
        BonusSpinRepository $bonusSpinRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->spinRepository = $spinRepo;

        $this->memberRepository = $memberRepo;

        $this->bonusSpinRepository = $bonusSpinRepo;
    }

    public function index()
    {

        $spins = $this->loadSpin();

        $profile = $this->user();

        return view($this->_config['view'], compact('spins', 'profile'));
    }

    public function loadSpin(): Collection
    {
        $responses = collect($this->spinRepository->findWhere(['enable' => 'Y'])->toArray());
        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'fillStyle' => $item->spincolor,
                'image' => Storage::url('spin_img/' . $item->filepic),
                'text' => $item->name,
                'code' => $item->code,
                'amount' => $item->amount,
                'winloss' => $item->winloss,
                'spincolor' => $item->spincolor,
                'name' => $item->name,
                'types' => $item->types,
            ];

        });


        return $responses;
    }

    public function store(Request $request): JsonResponse
    {

        function getRandomWeightedElement(array $weightedValues)
        {
            $rand = mt_rand(1, (int)array_sum($weightedValues));

            foreach ($weightedValues as $key => $value) {
                $rand -= $value;
                if ($rand <= 0) {
                    return $key;
                }
            }
        }


        $ip = $request->ip();
        $config = core()->getConfigData();
        $maxbonus = $config->maxspin;
        $bonustoday_sum = $this->bonusSpinRepository->scopeQuery(function($query){
            return $query->where('enable','Y')->whereDate('date_create', now()->toDateString());
        });

        $bonustoday = $bonustoday_sum->sum('amount');





        $diamond = ($this->user()->diamond - 1);
        if ($diamond < 0) {
            return $this->sendError('ต้องใช้เพชรในการเล่น จำนวน 1 เม็ด', 200);
        }
        $result['success'] = 'COMPLETE';
        $spins = $this->LoadSpin();
        $percent = 0;

        $range = (360 / count($spins));

        $change = [];
        $no_change = [];
        $random = [];

        foreach ($spins as $i => $item) {

            $change += [$item['code'] => $item['winloss']];

            if ($item['amount'] == 0) {
                $no_change += [$item['code'] => $item['winloss']];
            }

            $percent += $item['winloss'];


            $start = (($i * $range) + 1);
            $stop = (($i + 1) * $range);

            $wheel[] = [
                'fillStyle' => ($item['spincolor'] ?: '#cccccc'),
                'text' => $item['name'],
                'image' => ''
            ];

            $data[] = [
                'name' => $item['name'],
                'amount' => $item['amount'],
                'start' => $start,
                'stop' => $stop,
                'types' => $item['types']
            ];

            $random[$item['code']] = [
                'name' => $item['name'],
                'amount' => $item['amount'],
                'start' => $start,
                'stop' => $stop,
                'types' => $item['types']
            ];

        }

        if ($bonustoday > $maxbonus) {
//            dd($no_change);
            $spinid = getRandomWeightedElement($no_change);
        } else {
            $spinid = getRandomWeightedElement($change);
        }

        $point = rand($random[$spinid]['start'], $random[$spinid]['stop']);

        $name_stop = $random[$spinid]['name'];
        $amount_stop = $random[$spinid]['amount'];
        $reward_type = $random[$spinid]['types'];


        $setdata = [
            'remark' => 'ร่วมสนุกการหมุนวงล้อ',
            'amount' => 1,
            'method' => 'W',
            'member_code' => $this->id(),
            'emp_code' => 0,
            'emp_name' => $this->user()->name
        ];

        //หัก ไดม่อน 1 เม็ด
        $response = app('Gametech\Member\Repositories\MemberDiamondLogRepository')->setDiamond($setdata);
        if (!$response) {
            return $this->sendError('ไม่สามารถทำรายการได้', 200);
        }

        //สร้าง รายการหมุนวงล้อ
        $param = [
            'member_code' => $this->id(),
            'bonus_name' => $name_stop,
            'reward_type' => $reward_type,
            'amount' => $amount_stop,
            'credit_before' => 0,
            'credit_after' => 0,
            'diamond_balance' => $diamond,
            'ip' => $ip,
            'user_create' => $this->user()->name,
            'user_update' => $this->user()->name
        ];

        try {
            $response = $this->bonusSpinRepository->create($param);
        } catch (Throwable $e) {
            report($e);
            return $this->sendError('ไม่สามารถทำรายการได้', 200);
        }


        if ($reward_type === 'WALLET' && $amount_stop > 0) {
            $setdata = [
                'kind' => 'SPIN',
                'remark' => 'ได้รับรางวัลจากการหมุนวงล้อ',
                'amount' => $amount_stop,
                'method' => 'D',
                'member_code' => $this->id(),
                'refer_code' => $response->code,
                'refer_table' => 'bonus_spin',
                'emp_code' => 0,
                'emp_name' => $this->user()->name
            ];

            $response = app('Gametech\Member\Repositories\MemberCreditLogRepository')->setWallet($setdata);
            if (!$response) {
                return $this->sendError('ไม่สามารถทำรายการได้', 200);
            }
        } elseif ($reward_type === 'CREDIT' && $amount_stop > 0) {
            $setdata = [
                'kind' => 'SPIN',
                'remark' => 'ได้รับรางวัลจากการหมุนวงล้อ',
                'amount' => $amount_stop,
                'method' => 'D',
                'member_code' => $this->id(),
                'emp_code' => 0,
                'emp_name' => $this->user()->name
            ];

            $response = app('Gametech\Member\Repositories\MemberFreeCreditRepository')->setCredit($setdata);
            if (!$response) {
                return $this->sendError('ไม่สามารถทำรายการได้', 200);
            }
        } elseif ($reward_type === 'DIAMOND' && $amount_stop > 0) {

            $setdata = [
                'remark' => 'ได้รับรางวัลจากการหมุนวงล้อ',
                'amount' => $amount_stop,
                'method' => 'D',
                'member_code' => $this->id(),
                'emp_code' => 0,
                'emp_name' => $this->user()->name
            ];

            $response = app('Gametech\Member\Repositories\MemberDiamondLogRepository')->setDiamond($setdata);
            if (!$response) {
                return $this->sendError('ไม่สามารถทำรายการได้', 200);
            }
        }


        if ($amount_stop > 0) {
            $win = [
                'title' => 'ได้รางวัล !! โชคของคุณมาแล้ววว',
                'msg' => 'จากการหมุนวงล้อมหาสนุก ได้รับ ' . $name_stop . ' ( จำนวน ' . core()->currency($amount_stop) . ' )',
                'img' => Storage::url('spin_img/spin-win.png'),
                'point' => $point,
                'diamond' => $diamond
            ];
        } else {

//            $word[1] = [ 'title' => 'รอบนี้โชคยังมาไม่ถึง' , 'msg' => 'ลองอีกทีดูไหม โชคดีอาจจะมารอบหน้า' ];
//            $word[2] = [ 'title' => 'หยุดดดด !! พลาดรางวัลไปซะแล้ว' , 'msg' => 'เกือบแล้วๆ เพชรยังมีให้ลุ้นอีกไหม' ];
//
            $win = [
                'title' => 'รอบนี้โชคยังมาไม่ถึง',
                'msg' => 'ลองอีกทีดูไหม โชคดีอาจจะมารอบหน้า',
                'img' => Storage::url('spin_img/spin-loss.png'),
                'point' => $point,
                'diamond' => $diamond
            ];
        }

        $result['diamond'] = $diamond;
        $result['format'] = $win;
        $result['spin'] = $wheel;
        return $this->sendResponseNew($result, 'complete');
    }


    public function history()
    {
        $histories = $this->loadHistory();

        return view($this->_config['view'], compact('histories'));
    }

    public function loadHistory(): array
    {
        $responses = [];
        $result = [];
        $results = $this->user()->bonus_spin()->select('bonus_name', 'reward_type', 'amount', DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"), DB::raw("DATE_FORMAT(date_create,'%H:%i') as time"))->orderBy('code', 'desc')->withCasts([
            'date' => 'date:d/m/Y'
        ])->get()->toArray();

        foreach ($results as $item) {

            if ($item['amount'] > 0) {
                $credit = 'ได้รับรางวัล ' . $item['bonus_name'] . ' จำนวน ' . $item['amount'];
            } else {
                $credit = 'ไม่ได้รับรางวัล';
            }

            $responses[$item['date']]['date'] = $item['date'];
//            $responses[$item['date']]['date'] = core()->formatDate($item['date'],'d/m/Y');
            $responses[$item['date']]['data'][] = ['credit' => $credit, 'time' => $item['time']];
        }

        foreach ($responses as $value) {
            $result[] = $value;
        }

        return $result;
    }


}
