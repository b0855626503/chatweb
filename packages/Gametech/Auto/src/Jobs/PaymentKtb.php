<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\Ktb;
use Gametech\Core\Repositories\AllLogRepository;
use Gametech\Core\Repositories\ConfigRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankAccountRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\PaymentPromotionRepository;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sunra\PhpSimple\HtmlDomParser;

class PaymentKtb
{
    use Dispatchable;


    protected $bankPaymentRepository;

    protected $memberRepository;

    protected $configRepository;

    protected $paymentPromotionRepository;

    protected $allLogRepository;

    protected $bankAccountRepository;

    protected $account;


    public function __construct
    (
        $account,
        BankPaymentRepository $bankPayment,
        MemberRepository $memberRepo,
        ConfigRepository $configRepo,
        PaymentPromotionRepository $paymentPromotionRepo,
        BankAccountRepository $bankAccountRepo,
        AllLogRepository $allLogRepo
    )
    {
        $this->bankPaymentRepository = $bankPayment;

        $this->memberRepository = $memberRepo;

        $this->configRepository = $configRepo;

        $this->paymentPromotionRepository = $paymentPromotionRepo;

        $this->allLogRepository = $allLogRepo;

        $this->bankAccountRepository = $bankAccountRepo;

        $this->account = $account;
    }


    public function handle()
    {
        $config = new Ktb();

        $datenow = now()->toDateTimeString();
        $account =  $this->account;

        $bank = $this->bankAccountRepository->getAccountOne('ktb',$account);

        $path = storage_path('auto');
        $cookie = $path.'/ktb/ktb-cookies'.$bank->code;
        if (file_exists($cookie)) {
            $x = 1400; //0.5 hours 1800
            $current_time = time();
            $file_creation_time = filemtime($cookie);
            $difference = $current_time - $file_creation_time;
            if ($difference >= $x) {
                unlink($cookie);
            }

            if (file_exists($path . '/ktb/ktbpara' . $bank->code . '.txt')) {
                //unlink($PATH . '/ktbpara' . $bankaccount->code . '.txt');
            }
        }

        $username = $bank->user_name;
        $password = $bank->user_pass;
        $account_name = $account;

        $option = [
            'connect_timeout' => 30,
            'timeout' => 120,
            'cookies' => $cookie,
            'cert' => $path . '/cacert.pem',
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.6 (KHTML, like Gecko) Chrome/16.0.897.0 Safari/535.6'
            ]
        ];


        $baseurl = 'https://www.ktbnetbank.com';

        $session_key = '';
        if (file_exists($path . "/ktb/ktbpara" . $bank->code . ".txt")) {
            $myfile = fopen($path . "/ktb/ktbpara" . $bank->code . ".txt", "r") or die("Unable to open file!");
            $session_key = fread($myfile, filesize($path . "/ktb/ktbpara" . $bank->code . ".txt"));
            fclose($myfile);
        }
        $account_name = Str::of($account_name)->replace('-', '');
        if (isset($session_key) && $session_key != '') {
            $r = microtime(true);
            $response = Http::withOptions($option)->get($baseurl. '/consumer/SavingAccount.do?cmd=init&sessId=' . $session_key . '&_=' . $r);
            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                $bank->balance = Str::of($xml->DATA->AMOUNT)->replace(',', '');
            }

        }

        if (empty($xml) || strpos($response->body(), 'Your session has been expired, please log in again') !== false || strpos($response->body(), 'Your session has been terminated, please Close Page') !== false) {

            //Login KTB
            $response = Http::withOptions($option)->get($baseurl. '/consumer/');
            if ($response->successful()) {
                $response = Http::withOptions($option)->get($baseurl. '/consumer/captcha/verifyImg');
                if ($response->successful()) {
                    $html = HtmlDomParser::str_get_html($response->body());
                    $form_field = array();
                    foreach ($html->find('form input') as $element) {
                        $form_field[$element->name] = $element->value;
                    }

                    $img = $path . '/ktb/capktb' . $bank->code . '.png';
                    if (file_exists($img)) {
                        unlink($img);
                    }
                    $fp = fopen($img, 'w');
                    fwrite($fp, $response->body());
                    fclose($fp);
                    $captcha['text'] = '';
                    if (strlen($captcha['text']) < 4 || is_numeric($captcha['text']) == false) {
                        $captcha['text'] = $config->twocaptcha($img);
                    }
                    $form_field['imageCode'] = $captcha['text'];
                    $form_field['userId'] = $username;
                    $form_field['password'] = $password;
                    $form_field['cmd'] = 'login';
                    $post_string = '';
                    foreach ($form_field as $key => $value) {
                        $post_string .= $key . '=' . urlencode($value) . '&';
                    }
                    $post_string = substr($post_string, 0, -1);

                    $options = collect($option)->merge(['headers' => ['Referer' => $baseurl.'/consumer/']])->all();

                    $response = Http::withOptions($options)->post($baseurl. '/consumer/Login.do',$post_string);
                    if ($response->successful()) {

                        preg_match("/sessionKey = '(.*)'/", $response->body(), $output_array);
                        $session_key = $output_array[1];
                        if (file_exists($path . '/ktb/ktbpara' . $bank->code . '.txt')) {
                            unlink($path . '/ktb/ktbpara' . $bank->code . '.txt');
                        }
                        $fp = fopen($path . '/ktb/ktbpara' . $bank->code . '.txt', 'w'); //getcwd().
                        fwrite($fp, $session_key);
                        fclose($fp);

                        $r = microtime(true);
                        $response = Http::withOptions($option)->get($baseurl. '/consumer/SavingAccount.do?cmd=init&sessId=' . $session_key . '&_=' . $r);
                        if ($response->successful()) {
                            $xml = simplexml_load_string($response->body());
                            $bank->balance = str_replace(',', '', $xml->DATA->AMOUNT);


                            $account_name = str_replace('-', '', $account_name);
                            $date_period = now()->format('d-m-Y');
                            $form_field = array();
                            $form_field['acctNo'] = $account_name;
                            $form_field['fromDate'] = $date_period;
                            $form_field['radio'] = '1';
                            $form_field['sessId'] = $session_key;
                            $form_field['specificAmtFrom'] = '';
                            $form_field['specificAmtTo'] = '';
                            $form_field['toDate'] = $date_period;
                            $form_field['txnRefNoFrom	'] = '';
                            $form_field['txnRefNoTo'] = '';
                            $r = microtime(true);

                            $options = collect($option)->merge(['headers' => ['Referer' => $baseurl.'/consumer/main.jsp']])->all();

                            $response = Http::withOptions($options)->post($baseurl. '/consumer/SearchSpecific.do?cmd=search&r=' . $r,$form_field);
                            if ($response->successful()) {
                                $html = HtmlDomParser::str_get_html($response->body());
                                $table = $html->find('table.subcontenttable', 0);

                                $s = 'วันที่';
                                $total = array();
                                if (!(empty($table))) {
                                    $row = 0;
                                    foreach ($table->find('tr') as $tr) {
                                        $td1 = $config->clean($tr->find('td', 0)->plaintext);
                                        $pos = strpos($td1, $s);
                                        if ($pos !== false) {
                                            continue;
                                        }
                                        $info = "";
                                        $info = $config->clean($tr->find('td', 1)->plaintext) . ' ' . $config->clean($tr->find('td', 6)->plaintext);
                                        $pos = strpos($info, "Future");
                                        if ($pos !== false) {
                                            continue;
                                        }
                                        $amount = (float) str_replace(',', '', $config->clean($tr->find('td', 3)->plaintext));
                                        $pos = strpos($info, "Fee");
                                        if ($pos !== false && $amount < 0 && $amount > -50) {
                                            $total[$row - 1]['in'] = $total[$row - 1]['in'] + $amount;
                                            continue;
                                        }
                                        $list = array();
                                        $list['date'] = date("Y-m-d H:i:s", strtotime(substr($td1, 0, -3)));
                                        $list['in'] = $amount;
                                        $list['info'] = $info;
                                        $list['out'] = '';
                                        $from_acc_no = '';
                                        $accno = explode('-', $config->clean($tr->find('td', 1)->plaintext));
                                        if (count($accno) == 2) {
                                            $from_acc_no = $accno[1];

                                        } else {
                                            $accno = explode('TR fr ', $config->clean($tr->find('td', 1)->plaintext));
                                            if (count($accno) == 2) {
                                                $from_acc_no = $accno[1];
                                            }
                                        }

                                        $list['from_acc_no'] = $from_acc_no;
                                        $list['channel'] = "";
                                        if (empty($list['in']) || $list['in'] < 0) {
                                            continue;
                                        }
                                        $total[] = $list;
                                        $row++;
                                    }
                                }


                                if(!is_null($total)) {
                                    $bankkey = $bank->bank->shortcode; // . $account_code;
                                    $staffkey = $bankkey . 'AUTO';
                                    $staff = $staffkey . '1';
                                    $reccord = array();
                                    foreach ($total as $row) {

                                        if ($row["in"] == "" && $row["out"] == "") {
                                            continue;
                                        }
                                        $value = $row["in"] > 0 ? $row["in"] : "-" . str_replace("-", "", $row["out"]);
                                        $rechk = $this->bankPaymentRepository->findOneWhere(['account_code' => $bank->acc_no, 'bank_time' =>  $row['date'] , 'value' => $value , 'detail' => $row["info"]]);
                                        if ($rechk->doesntExist()) {

                                            $dataadd = [
                                                'account_code' =>  $bank->acc_no,
                                                'bankname' =>  $bank->bank->shortcode,
                                                'bank' =>  strtolower($bank->bank->shortcode) . '_' . $bank->acc_no,
                                                'bankstatus' =>  $row["in"] > 0 ? 1 : 0,
                                                'atranferer' => $row['from_acc_no'],
                                                'bank_time' => $row["date"],
                                                'detail' => $row["info"],
                                                'value' => $value,
                                                'channel' =>  $row['channel'],
                                                'create_by' => 'AUTO'
                                            ];

                                            $this->bankPaymentRepository->create($dataadd);

                                            $bank->job_process = 'success';
                                            $bank->job_detail = 'บันทึกข้อมูลแล้ว';
                                            $bank->job_date = $datenow;
                                            $bank->checktime = $datenow;
                                            $bank->save();
                                        }
                                    }
                                }




                            }
                            $bank->job_process = 'error';
                            $bank->job_detail = 'เชื่อมต่อ SearchSpecific ผิดพลาด';
                            $bank->job_date = $datenow;
                            $bank->save();
                        }
                        $bank->job_process = 'error';
                        $bank->job_detail = 'เชื่อมต่อ SavingAccount ผิดพลาด';
                        $bank->job_date = $datenow;
                        $bank->save();
                    }
                    $bank->job_process = 'error';
                    $bank->job_detail = 'เชื่อมต่อ Login ผิดพลาด';
                    $bank->job_date = $datenow;
                    $bank->save();
                }
                $bank->job_process = 'error';
                $bank->job_detail = 'เชื่อมต่อ captcha ผิดพลาด';
                $bank->job_date = $datenow;
                $bank->save();
            }
            $bank->job_process = 'error';
            $bank->job_detail = 'เชื่อมต่อ consumer ผิดพลาด';
            $bank->job_date = $datenow;
            $bank->save();
        }
    }
}
