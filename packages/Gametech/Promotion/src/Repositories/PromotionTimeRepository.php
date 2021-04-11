<?php

namespace Gametech\Promotion\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Support\Facades\DB;

class PromotionTimeRepository extends Repository
{

    public function promotion($id,$datenow)
    {
        $today = substr($datenow, 0, 10);
        $result = $this->orderBy('time_start','desc')->where('pro_code',$id)->active()->whereRaw("? between time_start and time_stop",[$datenow])->select(DB::raw("CONCAT('$today',time_start,':00') as time_start , CONCAT('$today',time_stop,':00') as time_stop , amount"));
        if($result->exists()){
            return ['amount' => ($result->first()->value('amount') * 1) ];
        }
        return ['amount' => 0 ];
    }
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Promotion\Contracts\PromotionTime';
    }
}
