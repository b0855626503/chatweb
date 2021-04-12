<?php

namespace Gametech\Member\Repositories;

use Gametech\Core\Eloquent\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MemberRepository extends Repository
{

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Member\Contracts\Member';
    }


    public function getAff($id)
    {

        return $this->withCount(['downs' => function ($query) {
            $query->where('enable','Y');
        }])->withSum(['paymentsPromotion:credit_bonus' => function ($query) {
            $query->active()->aff();
        }])->find($id);
    }

    public function getAffTest($id)
    {

        return $this->withCount(['down' => function ($query) {
            $query->where('enable','Y');
        }])->withSum(['paymentsPromotion:credit_bonus' => function ($query) {
            $query->active()->aff();
        }])->find($id);
    }

    public function getPro($id): int
    {
        $result = $this->find($id)->bankPayments()->orderBy('date_create', 'desc')->complete()->active()->income()->uncheck()->where('bankstatus', 1)->value('value');

        if (is_null($result)) {
            $result = 0;
        }
        return $result;
    }

    public function sumBonus($id, $date = null)
    {
        return $this->withSum(['bonus_spin:amount' => function ($query) use ($date) {
            if ($date) {
                $query->whereDate('date_create', $date);
            }
            $query->active();
        }])->find($id);

    }

    public function sumPromotion($id)
    {
        return $this->withSum(['paymentsPromotion:credit_bonus' => function ($query) {
            $query->active()->aff();
        }])->find($id);
    }

    public function sumWithdraw($id, $date = null)
    {

        return $this->with('bank')->withSum(['withdraw:amount' => function ($query) use ($date) {
            if ($date) {
                $query->whereDate('date_create', $date);

            }
            $query->active()->whereIn('status', [0, 1]);
        }])->find($id);
    }

    public function sumWithdrawFree($id, $date = null)
    {
        return $this->with('bank')->withSum(['withdrawFree:amount' => function ($query) use ($date) {
            if ($date) {
                $query->whereDate('date_create', $date);

            }
            $query->active()->whereIn('status', [0, 1]);
        }])->find($id);
    }

    public function sumBillFree($id)
    {
        return $this->with('bank')->withSum(['billsFree:amount' => function ($query) {

            $query->active()->where('transfer_type', 2);
        }])->find($id);
    }

    public function loadBill($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->bills()->active()->orderBy('date_create', 'desc')->with(['game', 'promotion'])
            ->select(['bills.*'])
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('bills.date_create', [$date_start, $date_stop]);

            })->get();

    }

    public function loadBillFree($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->billsFree()->active()->orderBy('date_create', 'desc')->with(['game', 'promotion'])
            ->select(['bills_free.*'])
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('bills_free.date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();

    }

    public function loadWithdraw($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->withdraw()->active()->orderBy('date_create', 'desc')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();

    }

    public function loadWithdrawFree($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->withdrawFree()->active()->orderBy('date_create', 'desc')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();

    }

    public function loadDeposit($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->bankPayments()->income()->active()->orderBy('date_create', 'desc')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();


    }

    public function loadCashback($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->memberFreeCredit()->active()->orderBy('date_create', 'desc')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();

    }

    public function loadIC($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->memberIc()->active()->orderBy('date_cashback', 'desc')->with('down')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
//                return $query->whereRaw("DATE_FORMAT(date_cashback,'%Y-%m-%d') between ? and ?",[$date_start,$date_stop]);
            })->get();


    }

    public function loadDownline($id, $date_start = null, $date_stop = null)
    {
        return $this->find($id)->paymentsPromotion()->active()->orderBy('date_create', 'desc')->with('down')->whereHas('down')
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereBetween('date_create', [$date_start, $date_stop]);
            })->get();

    }

    public function loadAccount($bank, $data)
    {
        $member_code = null;
        $bankcode = $data->bank_account->bank->code;

        switch ($bank) {
            case 'bay':
                $field = "acc_bay = ?";
                $value = Str::substr($data->detail, -7);

                break;
            case 'tw':
                $field = "tel = ?";
                $value = Str::of($data->detail)->trim();

                break;
            case 'scb':
//                $field = "bank_code = 4 and name = ?";
//                $value = Str::of($data->detail)->after('นาย')->after('นาง')->after('นายสาว')->trim()->__toString();

                $field = "acc_check = ?";
                $acc = Str::of($data->atranferer)->replaceMatches('/[^0-9]/', '')->trim();
                $value = Str::of($acc)->replace('*', '');
                if(!$data->atranferer && !$value){
                    $field = "bank_code = 4 and name like ?";
                    $value = Str::of($data->detail)->after('นาย')->after('นาง')->after('นายสาว')->trim();
                    $value = "%{$value}%";
                }
                break;
            case 'kbank':
                $field = "bank_code = 2 and LENGTH(acc_no) = 10 and SUBSTRING(acc_no, 4, 6) = ?";
                $value = Str::of($data->atranferer)->replace('*', '');


                break;
            case 'ktb':
                $field = "acc_no = ?";
                $acc = Str::of($data->atranferer)->replaceMatches('/[^0-9]/', '')->trim();
                $value = Str::of($acc)->replace('*', '');

                break;
        }

        $satang = $this->getSatang($data->value);
        if ($satang > 0) {
            $result = app('Gametech\Member\Repositories\MemberSatangRepository')->findWhere(['bank_code' => $bankcode, 'shortcode' => $value, 'value' => $satang]);
            if ($result->count() == 1) {
                $member_code = $result->first()->member_code;
            }
        }

        return $this->whereRaw($field, [$value])->where('enable','Y')
            ->when($member_code, function ($query) use ($member_code) {
                return $query->where('code', $member_code);
            })->get();
    }

    public function getSatang($amount)
    {

        $satang = explode('.', $amount);
        if (count($satang) == 2) {
            return $satang[1];
        }
        return 0;
    }


}
