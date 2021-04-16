<?php

namespace Gametech\Promotion\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Repositories\MemberPromotionLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PromotionRepository extends Repository
{
    protected $memberRepository;
    protected $memberPromotionLogRepository;
    protected $promotionTimeRepository;
    protected $promotionAmountRepository;


    /**
     * PromotionRepository constructor.
     * @param MemberRepository $memberRepository
     * @param PromotionTimeRepository $promotionTimeRepository
     * @param PromotionAmountRepository $promotionAmountRepository
     * @param MemberPromotionLogRepository $memberPromotionLogRepository
     * @param App $app
     */
    public function __construct
    (
        MemberRepository $memberRepository,
        PromotionTimeRepository $promotionTimeRepository,
        PromotionAmountRepository $promotionAmountRepository,
        MemberPromotionLogRepository $memberPromotionLogRepository,

        App $app
    )
    {

        $this->memberRepository = $memberRepository;
        $this->memberPromotionLogRepository = $memberPromotionLogRepository;
        $this->promotionTimeRepository = $promotionTimeRepository;
        $this->promotionAmountRepository = $promotionAmountRepository;
        parent::__construct($app);
    }

    public function checkSelectPro($pro_id, $member_id, $amount, $date)
    {
        $promotion = [
            'pro_code' => 0,
            'pro_name' => '',
            'turnpro' => 0,
            'withdraw_limit' => 0,
            'bonus' => 0,
            'total' => $amount,
        ];

        $pro = $this->findOrFail($pro_id);
        $member = $this->memberRepository->findOrFail($member_id);


        switch ($pro->id) {
            case 'pro_newuser':
                if ($member->status_pro === 0) {
                    $promotion = $this->checkPromotion($pro_id, $amount, $date);
                }
                break;

            case 'pro_firstday':
                if ($this->checkProFirstDay($member->code) == 0) {
                    $promotion = $this->checkPromotion($pro_id, $amount, $date);
                }
                break;

            case 'pro_bigbonus':
            case 'pro_allbonus':
                $promotion = $this->checkPromotion($pro_id, $amount, $date);
                break;

            case 'pro_hottime';
//                if ($this->checkHotTime($today, '00:00', '00:01', $datenow)) {
//                    $promotion = $this->checkPromotion($pro_id, $amount, $date);
//                }
                break;
        }

    }


    public function checkPromotion($id, $amount, $date)
    {
        $min = 0;
        $pro_amount = 0.00;
        $order = array();

        $promotion = $this->findOrFail($id);

        if (!empty($promotion)) {
            if ($amount < $promotion->bonus_min) {
                $pro_amount = 0.00;
            } else {
                switch ($promotion->length_type) {
                    case 'PRICE':
                        $pro_amount = $promotion->bonus_price;
                        break;
                    case 'PERCENT':
                        $pro_amount = $amount * $promotion->bonus_percent / 100;
                        break;
                    case 'TIME':
                        $order = $this->promotionTimeRepository->promotion($id, $date);
                        $pro_amount = $order['amount'];
                        break;
                    case 'TIMEPC':
                        $order = $this->promotionTimeRepository->promotion($id, $date);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'AMOUNT':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'AMOUNTPC':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'BETWEEN':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'BETWEENPC':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                }
                if ($pro_amount > $promotion->bonus_max) {
                    $pro_amount = $promotion->bonus_max;
                }
            }
        }


        $type = [
            '' => '-',
            'PRICE' => 'จ่ายเป็น บาท',
            'PERCENT' => 'จ่ายเป็น %',
            'TIME' => 'ช่วงเวลา จ่ายเป็น บาท',
            'TIMEPC' => 'ช่วงเวลา จ่ายเป็น %',
            'AMOUNT' => 'ช่วงราคาตรงกัน จ่ายเป็น บาท',
            'AMOUNTPC' => 'ช่วงราคาตรงกัน จ่ายเป็น %',
            'BETWEEN' => 'ช่วงระหว่างราคา จ่ายเป็น บาท',
            'BETWEENPC' => 'ช่วงระหว่างราคา จ่ายเป็น %'
        ];

        if($pro_amount > 0) {
            $total = ($amount + $pro_amount);

            $result['pro_code'] = $id;
            $result['pro_name'] = $promotion->name_th;
            $result['turnpro'] = $promotion->turnpro;
            $result['withdraw_limit'] = $promotion->withdraw_limit;
            $result['total'] = $total;
            $result['bonus'] = $pro_amount;
        } else {
            $result['pro_code'] = 0;
            $result['pro_name'] = '';
            $result['turnpro'] = 0;
            $result['withdraw_limit'] = 0;
            $result['total'] = $amount;
            $result['bonus'] = 0;
        }
        $result['type'] = $type[$promotion->length_type];

        return $result;
    }

    public function checkPromotionId($pro_id, $amount, $date)
    {
        $min = 0;
        $pro_amount = 0.00;
        $order = array();

        $promotion = $this->findOneByField('id', $pro_id);
        $id = $promotion->code;


        if (!empty($promotion)) {
            if ($amount < $promotion->bonus_min) {
                $pro_amount = 0.00;
            } else {
                switch ($promotion->length_type) {
                    case 'PRICE':
                        $pro_amount = $promotion->bonus_price;
                        break;
                    case 'PERCENT':
                        $pro_amount = ($amount * $promotion->bonus_percent) / 100;
                        break;
                    case 'TIME':
                        $order = $this->promotionTimeRepository->promotion($id, $date);
                        $pro_amount = $order['amount'];
                        break;
                    case 'TIMEPC':
                        $order = $this->promotionTimeRepository->promotion($id, $date);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'AMOUNT':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'AMOUNTPC':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'BETWEEN':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'BETWEENPC':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                }
                if ($pro_amount > $promotion->bonus_max) {
                    $pro_amount = $promotion->bonus_max;
                }
            }
        }


        $type = [
            '' => '-',
            'PRICE' => 'จ่ายเป็น บาท',
            'PERCENT' => 'จ่ายเป็น %',
            'TIME' => 'ช่วงเวลา จ่ายเป็น บาท',
            'TIMEPC' => 'ช่วงเวลา จ่ายเป็น %',
            'AMOUNT' => 'ช่วงราคาตรงกัน จ่ายเป็น บาท',
            'AMOUNTPC' => 'ช่วงราคาตรงกัน จ่ายเป็น %',
            'BETWEEN' => 'ช่วงระหว่างราคา จ่ายเป็น บาท',
            'BETWEENPC' => 'ช่วงระหว่างราคา จ่ายเป็น %'
        ];

        if($pro_amount > 0) {
            $total = ($amount + $pro_amount);

            $result['pro_code'] = $id;
            $result['pro_name'] = $promotion->name_th;
            $result['turnpro'] = $promotion->turnpro;
            $result['withdraw_limit'] = $promotion->withdraw_limit;
            $result['total'] = $total;
            $result['bonus'] = $pro_amount;
        } else {
            $result['pro_code'] = 0;
            $result['pro_name'] = '';
            $result['turnpro'] = 0;
            $result['withdraw_limit'] = 0;
            $result['total'] = $amount;
            $result['bonus'] = 0.00;
        }
        $result['type'] = $type[$promotion->length_type];

        return $result;
    }


    public function loadPromotion($id)
    {
        $datenow = now();
        $today = now()->toDateString();

        $member = $this->memberRepository->find($id);
        $count = $this->checkProFirstDay($id);
        $hottime = $this->checkHotTime($today, '00:00', '00:01', $datenow);


        $code[] = '0';
        $code[] = '6';
        $code[] = '7';

        if ($member->status_pro == 1) {
            $code[] = '1';
        }

        if ($count) {
            $code[] = '2';
        }
        if (!$hottime) {
            $code[] = '5';
        }

        return $this->where('use_wallet', 'Y')->whereNotIn('code', $code)->active()->get();

    }

    public function checkProFirstDay($id)
    {
        $today = now()->toDateString();
        $member = $this->memberRepository->find($id);
        return $member->bills()->where('pro_code', 2)->whereDate('date_create', $today)->exists();

    }

    public function checkHotTime($today, $time_start, $time_stop, $datenow)
    {
        $datestart = $today . ' ' . $time_start . ':00';
        $datestop = $today . ' ' . $time_stop . ':00';
        $hot = DB::select("select '$datenow' as datenow,'$datestart' as datestart,'$datestop' as datestop  from dual where ? between ? and ?", [$datenow, $datestart, $datestop]);
        if (is_null($hot)) {
            return false;
        }
        return true;

    }

    public function CalculatePro($member, $amount, $date)
    {
        $bonus = 0;
        $pro_code = 0;
        $total = $amount;
        $status_pro = $member['status_pro'];
        $pro_name = '';
        $withdraw_limit = 0;
        $turnpro = 0;
        // Check Member Get Promotion (for single mode)
        if ($member['promotion'] == 'Y') {

            // Pro New User for First Deposit
            if ($status_pro == 0) {
                $promotion = $this->checkPromotionId('pro_newuser', $amount, $date);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $pro_name = $promotion['name'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $turnpro = $promotion['turnpro'];

                if ($bonus > 0) {
                    $status_pro = 1;
                }
            }

            // Pro First Deposit of Day
            $count_firstday = $this->checkProFirstDay($member['code']);
            if ($count_firstday == 0 && $bonus == 0) {
                $promotion = $this->checkPromotionId('pro_firstday', $amount, $date);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $pro_name = $promotion['name'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $turnpro = $promotion['turnpro'];
            }

            // Pro Big Bonus
            if ($bonus == 0) {
                $promotion = $this->checkPromotionId('pro_allbonus', $amount, $date);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $pro_name = $promotion['name'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $turnpro = $promotion['turnpro'];
            }

        }

        $result['bonus'] = $bonus;
        $result['pro_code'] = $pro_code;
        $result['total'] = $total;
        $result['status_pro'] = $status_pro;

        $result['pro_name'] = $pro_name;
        $result['turnpro'] = $turnpro;
        $result['withdraw_limit'] = $withdraw_limit;


        return $result;

    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Promotion\Contracts\Promotion';
    }

    public function createnew(array $data)
    {
        $reward = $this->create($data);

        $order = $this->find($reward->code);


        $this->uploadImages($data, $order);


        return $order;
    }

    public function updatenew(array $data, $id, $attribute = "id")
    {
        $order = $this->find($id);

        $order->update($data);


        $this->uploadImages($data, $order);


        return $order;
    }

    public function uploadImages($data, $order, $type = "filepic")
    {

        $request = request();

        $hasfile = is_null($request->fileupload);

        if (!$hasfile) {
            $file = $order->id . '.' . $request->fileupload->getClientOriginalExtension();
            $dir = 'promotion_img';

            Storage::putFileAs($dir, $request->fileupload, $file);
            $order->{$type} = $file;
            $order->save();

        }
    }
}
