<?php

namespace Gametech\Core;

use Carbon\Carbon;
use Exception;
use Gametech\Core\Repositories\ConfigRepository;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;

class Core
{

    protected $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    public function getConfigData()
    {
        return $this->configRepository->first();
    }

    /**
     * Format and convert price with currency symbol
     *
     * @param int $amount
     * @return string
     */
    public function currency($amount = 0, $decimal = 2)
    {
        if (is_null($amount)) {
            $amount = 0;
        }

        return number_format($amount, $decimal);

    }

    public function action_exists($action) {
        try {
            action($action);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function textcolor($text,$color='text-success'){

        return "<span class='$color'>".$text."</span>";

    }

    public function checkDisplay($text){

        if($text == 'Y'){
            return "<span class='text-success'><i class='fa fa-check'></i> Yes</span>";
        }else{
            return "<span class='text-muted'><i class='fa fa-times'></i> No</span>";
        }
    }

    public function showImg($img,$path,$width,$height,$class='rounded'){
        if(!$img){
            return '';
        }
        if($width != '' && $height != ''){
            return '<img src="'.Storage::url($path.'/'.$img).'" class="'.$class.'" style="width:'.$width.';height:'.$height.';">';
        }else{
            return '<img src="'.Storage::url($path.'/'.$img).'" class="'.$class.'">';
        }
    }

    public function imgurl($img,$path){
        if(!$img){
            return '';
        }

        return Storage::url($path.'/'.$img);

    }



    public function displayBtn($code,$method,$methodtxt){
        return '<button type="button" class="btn '.($method == 'Y' ? 'btn-success' : 'btn-danger').' btn-xs icon-only" onclick="editdata('. $code .",". "'".core()->flip($method)."'".","."'$methodtxt'" .')">'.($method == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>').'</button>';
    }

    public function displayBank($name,$pic){
        return $this->showImg($pic,'bank_img','20px','20px') .' '.$name;
    }


    /**
     * Format and convert price with currency symbol
     *
     * @param float $price
     *
     * @param $currency
     * @return string
     */
    public function formatPrice(float $price, $currency)
    {
        $region = config('app.locale').'_'.strtoupper(config('app.locale'));
        if (is_null($price))
            $price = 0;

        $formatter = new NumberFormatter($region, NumberFormatter::CURRENCY);
        return $formatter->parseCurrency($price, $currency);

    }

    public function TypeDisplay($type, $transfer,$remark, $bank, $game, $promotion)
    {
        $result = "";
        switch ($type) {
            case "TOPUP":
                $result = "<span class='text-success' data-toggle='popover' data-placement='top' data-content='$remark'>ฝากเงิน ($bank)</span>";
                break;
            case "WITHDRAW":
                $result = "<span class='text-danger' data-toggle='popover' data-placement='top' data-content='$remark'>ถอนเงิน ($bank)</span>";
                break;
            case "TRANSFER":
                if ($transfer == 'W') {
                    $result = "<span class='text-info' data-toggle='popover' data-placement='top' data-content='$remark'>Wallet ไป $game</span>";

                } elseif ($transfer == 'D') {
                    $result = "<span class='text-orange' data-toggle='popover' data-placement='top' data-content='$remark'>$game มา Wallet</span>";
                }
                break;
            case "SETWALLET":
                if ($transfer == 'D') {
                    $result = "<span class='text-success' data-toggle='popover' data-placement='top' data-content='$remark'>เพิ่ม Wallet</span>";
                } elseif ($transfer == 'W') {
                    $result = "<span class='text-danger' data-toggle='popover' data-placement='top' data-content='$remark'>ลด Wallet</span>";
                }
                break;
            case "ROLLBACK":
                $result = "<span class='text-success'>$remark</span>";
                break;
            case "SPIN":
                $result = "<span class='text-maroon' data-toggle='popover' data-placement='top' data-content='$remark'>วงล้อมหาสนุก</span>";
                break;
            case "FASTSTART":
                $result = "<span class='text-info' data-toggle='popover' data-placement='top' data-content='$remark'>$promotion</span>";
                break;
        }

        return $result;
    }


    /**
     * Check whether sql date is empty
     *
     * @param string $date
     *
     * @return bool
     */
    function is_empty_date(string $date)
    {
        return preg_replace('#[ 0:-]#', '', $date) === '';
    }

    /**
     * Format date using current channel.
     *
     * @param \Illuminate\Support\Carbon|null $date
     * @param string                          $format
     *
     * @return  string
     */
    public function formatDate($date = null, $format = 'd-m-Y H:i:s'): string
    {
        $timezone = config('app.timezone');
        $locale = config('app.locale');


        if (is_null($date)) {
            $date = Carbon::now();
        }


        $date = Carbon::parse($date,$timezone);
//        dd($date);
//        $date = Carbon::createFromFormat($format,$date,$timezone);
//        $date->setTimezone($timezone);


        return $date->format($format);
    }

    public function Date($date = null, $format = 'd-m-Y'): string
    {
        $timezone = config('app.timezone');
        $locale = config('app.locale');


        if (is_null($date)) {
            $date = Carbon::now();
        }
        $date = Carbon::parse($date,$timezone);

//        $date = Carbon::parse($date);
//        dd($date);
//        $date = Carbon::createFromFormat('Y-m-d',$date,$timezone);
//        $date->setTimezone($timezone);


        return $date->format($format);
    }

    public function flip($data){
        return ($data === 'Y' ? 'N' : 'Y');
    }

    /**
     * Returns time intervals
     *
     * @param \Illuminate\Support\Carbon $startDate
     * @param \Illuminate\Support\Carbon $endDate
     *
     * @return array
     */
    public function getTimeInterval(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate)
    {
        $timeIntervals = [];

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalMonths = $startDate->diffInMonths($endDate) + 1;

        $startWeekDay = Carbon::createFromTimeString($this->xWeekRange($startDate, 0) . ' 00:00:01');
        $endWeekDay = Carbon::createFromTimeString($this->xWeekRange($endDate, 1) . ' 23:59:59');
        $totalWeeks = $startWeekDay->diffInWeeks($endWeekDay);

        if ($totalMonths > 5) {
            for ($i = 0; $i < $totalMonths; $i++) {
                $date = clone $startDate;
                $date->addMonths($i);

                $start = Carbon::createFromTimeString($date->format('Y-m-d') . ' 00:00:01');
                $end = $totalMonths - 1 == $i
                    ? $endDate
                    : Carbon::createFromTimeString($date->format('Y-m-d') . ' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('M')];
            }
        } elseif ($totalWeeks > 6) {
            for ($i = 0; $i < $totalWeeks; $i++) {
                $date = clone $startDate;
                $date->addWeeks($i);

                $start = $i == 0
                    ? $startDate
                    : Carbon::createFromTimeString($this->xWeekRange($date, 0) . ' 00:00:01');
                $end = $totalWeeks - 1 == $i
                    ? $endDate
                    : Carbon::createFromTimeString($this->xWeekRange($date, 1) . ' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('d M')];
            }
        } else {
            for ($i = 0; $i < $totalDays; $i++) {
                $date = clone $startDate;
                $date->addDays($i);

                $start = Carbon::createFromTimeString($date->format('Y-m-d') . ' 00:00:01');
                $end = Carbon::createFromTimeString($date->format('Y-m-d') . ' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('d M')];
            }
        }

        return $timeIntervals;
    }

    /**
     *
     * @param string $date
     * @param int $day
     *
     * @return string
     */
    public function xWeekRange(string $date, int $day)
    {
        $ts = strtotime($date);

        if (! $day) {
            $start = (date('D', $ts) == 'Sun') ? $ts : strtotime('last sunday', $ts);

            return date('Y-m-d', $start);
        } else {
            $end = (date('D', $ts) == 'Sat') ? $ts : strtotime('next saturday', $ts);

            return date('Y-m-d', $end);
        }
    }

    /**
     * Method to sort through the acl items and put them in order
     *
     * @param array $items
     *
     * @return array
     */
    public function sortItems(array $items)
    {
        foreach ($items as &$item) {
            if (count($item['children'])) {
                $item['children'] = $this->sortItems($item['children']);
            }
        }

        usort($items, function ($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }

            return ($a['sort'] < $b['sort']) ? -1 : 1;
        });

        return $this->convertToAssociativeArray($items);
    }


    /**
     * @param array $items
     *
     * @return array
     */
    public function convertToAssociativeArray(array $items)
    {
        foreach ($items as $key1 => $level1) {
            unset($items[$key1]);
            $items[$level1['key']] = $level1;

            if (count($level1['children'])) {
                foreach ($level1['children'] as $key2 => $level2) {
                    $temp2 = explode('.', $level2['key']);
                    $finalKey2 = end($temp2);
                    unset($items[$level1['key']]['children'][$key2]);
                    $items[$level1['key']]['children'][$finalKey2] = $level2;

                    if (count($level2['children'])) {
                        foreach ($level2['children'] as $key3 => $level3) {
                            $temp3 = explode('.', $level3['key']);
                            $finalKey3 = end($temp3);
                            unset($items[$level1['key']]['children'][$finalKey2]['children'][$key3]);
                            $items[$level1['key']]['children'][$finalKey2]['children'][$finalKey3] = $level3;
                        }
                    }

                }
            }
        }

        return $items;
    }

    /**
     * @param $array
     * @param string $key
     * @param string|int|float $value
     *
     * @return array|float|int|string
     */
    public function array_set(&$array, string $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);
        count($keys);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $finalKey = array_shift($keys);

        if (isset($array[$finalKey])) {
            $array2 = (array)$value;
            $array[$finalKey] = $this->arrayMerge($array[$finalKey], $array2);
        } else {
            $array[$finalKey] = $value;
        }

        return $array;
    }

    /**
     * @param array $array1
     * @param array $array2
     *
     * @return array
     */
    protected function arrayMerge(array $array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param $array
     * @return array
     */
    public function convertEmptyStringsToNull($array)
    {
        foreach ($array as $key => $value) {
            if ($value == "" || $value == "null") {
                $array[$key] = null;
            }
        }

        return $array;
    }

    /**
     * Create singletom object through single facade
     *
     * @param string $className
     *
     * @return object
     */
    public function getSingletonInstance(string $className)
    {
        static $instance = [];

        if (array_key_exists($className, $instance)) {
            return $instance[$className];
        }

        return $instance[$className] = app($className);
    }

    public function generateDateRange($start_date,$end_date)
    {
        $diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
        $day = array();
        for ($i = 0; $i <= $diff; $i++) {
            $daycheck = date('Y-m-d', strtotime($start_date . ' + ' . $i . ' days'));
            $day[] = $daycheck;
        }

        return $day;
    }

}
