<?php

namespace Gametech\Promotion\Repositories;

use Gametech\Core\Eloquent\Repository;


class PromotionTimeRepository extends Repository
{

    public function promotion($id, $datenow)
    {

        $result = $this->scopeQuery(function ($query) use ($id, $datenow) {
            return $query->orderBy('time_start', 'desc')->where('pro_code', $id)->where('enable', 'Y')->whereRaw("? between time_start and time_stop", [$datenow]);
        })->first('amount');

        if(empty($result)){
            return ['amount' => 0];
        }

        return $result;

//        $result = $this->orderBy('time_start', 'desc')->where('pro_code', $id)->active()->whereRaw("? between time_start and time_stop", [$datenow])->select(DB::raw("CONCAT('$today',time_start,':00') as time_start , CONCAT('$today',time_stop,':00') as time_stop , amount"));
//        if ($result->exists()) {
//            return ['amount' => ($result->first()->value('amount') * 1)];
//        }
//        return ['amount' => 0];
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Promotion\Contracts\PromotionTime';
    }
}
