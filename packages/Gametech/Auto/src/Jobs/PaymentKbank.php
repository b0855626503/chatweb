<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\Kbank;
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


class PaymentKbank
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
        $datenow = now()->toDateTimeString();
        $account = $this->account;

        $bank = $this->bankAccountRepository->getAccountOne('kbank', $account);

        $username = $bank->user_name;
        $password = $bank->user_pass;
        $account_name = Str::substr($account, 0, 3) . '-' . Str::substr($account, 3, 1);
        $accname = Str::of($account)->replace('-', '');
        $cookie = storage_path('auto/kbank/' . $accname);
        $path = storage_path('auto');

        if (!is_writable($cookie)) {
            echo 'Cookie file missing or not writable.';
            exit;
        }

        $config = new Kbank();


        $option = [
            'connect_timeout' => 30,
            'timeout' => 120,
            'cookies' => $cookie,
            'cert' => $path . '/cacert.pem',
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z‡ Safari/537.36")'
            ]
        ];

        $response = Http::withOptions($option)->get('https://online.kasikornbankgroup.com/K-Online/indexHome.jsp');

        if ($response->successful()) {

            $html = HtmlDomParser::str_get_html($response->body());
            $hasspan = $html->find('span#7', 0)->innertext;

            if ($hasspan != "ออกจากระบบ") {

                $form_field = array();
                $form_field['isConfirm	'] = 'T';
                $post_string = '';
                foreach ($form_field as $key => $value) {
                    $post_string .= $key . '=' . urlencode($value) . '&';
                }
                $post_string = substr($post_string, 0, -1);

                $response = Http::withOptions($option)->asForm()->post('https://online.kasikornbankgroup.com/K-Online/indexHome.jsp', $post_string);
                if ($response->successful()) {
                    $response = Http::withOptions($option)->get('https://online.kasikornbankgroup.com/K-Online/login.do');
                    if ($response->successful()) {
                        $html = HtmlDomParser::str_get_html($response->body());
                        $form_field = array();
                        foreach ($html->find('form input') as $element) {
                            $form_field[$element->name] = $element->value;
                        }
                        $form_field['userName'] = $username;
                        $form_field['password'] = $password;
                        $post_string = '';
                        foreach ($form_field as $key => $value) {
                            $post_string .= $key . '=' . urlencode($value) . '&';
                        }
                        $post_string = substr($post_string, 0, -1);


                        $options = collect($option)->merge(['headers' => ['Referer' => 'https://online.kasikornbankgroup.com/K-Online/login.do']])->all();
                        $response = Http::withOptions($options)->asForm()->post('https://online.kasikornbankgroup.com/K-Online/login.do', $post_string);
                        if ($response->successful()) {

                            $response = Http::withOptions($option)->get('https://online.kasikornbankgroup.com/K-Online/indexHome.jsp');
                            if ($response->successful()) {

                                $options = collect($option)->merge(['headers' => ['Referer' => 'https://online.kasikornbankgroup.com/K-Online/indexHome.jsp']])->all();
                                $response = Http::withOptions($options)->asForm()->post('https://online.kasikornbankgroup.com/K-Online/checkSession.jsp');
                                if ($response->successful()) {

                                    $options = collect($option)->merge(['headers' => ['Referer' => 'https://online.kasikornbankgroup.com/K-Online/indexHome.jsp']])->all();
                                    $response = Http::withOptions($options)->asForm()->post('https://online.kasikornbankgroup.com/K-Online/clearSession.jsp');
                                    if ($response->successful()) {

                                        $response = Http::withOptions($option)->get('https://online.kasikornbankgroup.com/K-Online/ib/redirectToIB.jsp?r=7027');
                                        if ($response->successful()) {
                                            $html = HtmlDomParser::str_get_html($response->body());
                                            $form_field = array();
                                            foreach ($html->find('form input') as $element) {
                                                $form_field[$element->name] = $element->value;
                                            }
                                            $post_string = '';
                                            foreach ($form_field as $key => $value) {
                                                $post_string .= $key . '=' . urlencode($value) . '&';
                                            }
                                            $post_string = substr($post_string, 0, -1);

                                            $response = Http::withOptions($option)->asForm()->post('https://ebank.kasikornbankgroup.com/retail/security/Welcome.do', $post_string);
                                            if ($response->successful()) {
                                                if (Str::of($response->body())->match('/.*?Unsuccessful Login.*?/')) {
                                                    $bank->job_process = 'error';
                                                    $bank->job_detail = 'ไม่สามารถ Login ได้';
                                                    $bank->job_date = $datenow;
                                                    $bank->save();
                                                    exit;
                                                }
                                            }
                                            $bank->job_process = 'error';
                                            $bank->job_detail = 'เชื่อมต่อ Welcome ผิดพลาด';
                                            $bank->job_date = $datenow;
                                            $bank->save();
                                        }
                                        $bank->job_process = 'error';
                                        $bank->job_detail = 'เชื่อมต่อ redirectToIB ผิดพลาด';
                                        $bank->job_date = $datenow;
                                        $bank->save();
                                    }
                                    $bank->job_process = 'error';
                                    $bank->job_detail = 'เชื่อมต่อ clearSession ผิดพลาด';
                                    $bank->job_date = $datenow;
                                    $bank->save();
                                }
                                $bank->job_process = 'error';
                                $bank->job_detail = 'เชื่อมต่อ checkSession ผิดพลาด';
                                $bank->job_date = $datenow;
                                $bank->save();
                            }
                            $bank->job_process = 'error';
                            $bank->job_detail = 'เชื่อมต่อ indexHome ผิดพลาด';
                            $bank->job_date = $datenow;
                            $bank->save();
                        }
                        $bank->job_process = 'error';
                        $bank->job_detail = 'เชื่อมต่อ login ผิดพลาด';
                        $bank->job_date = $datenow;
                        $bank->save();
                    }
                }
                $bank->job_process = 'error';
                $bank->job_detail = 'เชื่อมต่อ indexHome ผิดพลาด';
                $bank->job_date = $datenow;
                $bank->save();
            }

            $response = Http::withOptions($option)->get('https://ebank.kasikornbankgroup.com/retail/RetailWelcome.do');
            if ($response->successful()) {
                $html = HtmlDomParser::str_get_html($response->body());
                $s = "เลขที่บัญชี";
                $table = $html->find('table[rules="rows"]', 1);
                foreach ($table->find('tr') as $tr) {
                    $td1 = $config->clean($tr->find('td', 0)->plaintext);
                    $pos = strpos($td1, $s);
                    if ($pos !== false) {
                        continue;
                    }
                    //echo $accname."==".$td1;
                    if ($td1 == $account_name) {
                        $balance = floatval(preg_replace('/[^0-9\.\+\-]/', '', str_replace(",", "", $tr->find('td', 3)->plaintext)));
                        break;
                    }
                }

                if ($balance >= 0) {

                    $bank->balance = $balance;
                    $bank->checktime = $datenow;
                    $bank->save();

                } else {

                    $bank->checktime = $datenow;
                    $bank->save();

                }

                $response = Http::withOptions($option)->get('https://ebank.kasikornbankgroup.com/retail/cashmanagement/TodayAccountStatementInquiry.do');
                if ($response->successful()) {
                    $response = $response->body();
                    $response = iconv("windows-874", "utf-8", $response);
                    $html = HtmlDomParser::str_get_html($response);
                    $form_field = array();
                    foreach ($html->find('form[name="TodayStatementForm"] input') as $element) {
                        $form_field[$element->name] = $element->value;
                    }
                    $s = $account_name;
                    foreach ($html->find('select[name="acctId"] option') as $element) {
                        $text = $config->clean($element->plaintext);
                        $pos = strpos($text, $s);
                        if ($pos !== false) {
                            $form_field['acctId'] = $element->value;
                        }
                    }
                    $post_string = '';
                    foreach ($form_field as $key => $value) {
                        $post_string .= $key . '=' . urlencode($value) . '&';
                    }
                    $post_string = substr($post_string, 0, -1);
                    $response = Http::withOptions($option)->asForm()->post('https://ebank.kasikornbankgroup.com/retail/cashmanagement/TodayAccountStatementInquiry.do', $post_string);
                    if ($response->successful()) {
                        $total = array();
                        $s = 'วันที่';
                        $html = HtmlDomParser::str_get_html($response->body());
                        $table = $html->find('table[rules="rows"]', 0);
                        if (!(empty($table))) {
                            $dup = 0;
                            foreach ($table->find('tr') as $tr) {
                                $td1 = $config->clean($tr->find('td', 0)->plaintext);
                                $pos = strpos($td1, $s);
                                if ($pos !== false) {
                                    continue;
                                }

                                $list = array();

                                preg_match_all('/<td class=inner_table_.*?>\s?(.*?)<\/td>/', $tr, $temp2);
                                foreach ($temp2[1] as $key => $val) {
                                    switch ($key) {
                                        case 0:
                                            $val = str_replace('<br>', '', $val);
                                            $n = preg_split('/\s+/', substr($val, 0, -3));
                                            $ndate = explode("/", $n[0]);
                                            $list['time'] = strtotime('20' . $ndate[2] . '-' . $ndate[1] . '-' . $ndate[0] . ' ' . $n[1]);
                                            $list['date'] = '20' . $ndate[2] . '-' . $ndate[1] . '-' . $ndate[0] . ' ' . $n[1];
                                            break;
                                        case 1:
                                            $list['channel'] = $val;
                                            break;
                                        case 2:
                                            $list['detail'] = $val;
                                            break;
                                        case 3:
                                            if ($val != '') {
                                                $list['value'] = "-" . floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                            }
                                            break;
                                        case 4:
                                            if ($val != '') {
                                                $list['value'] = floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                            }
                                            break;
                                        case 5:
                                            $list['fee'] = floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                            break;
                                        case 6:
                                            $list['acc_num'] = str_replace(array('x', '-'), array('*', ''), $val);
                                            break;
                                        case 7:
                                            $list['detail'] .= ' (' . $val . ')';
                                            break;
                                    }

                                }
                                $list['value'] = str_replace(",", "", $list['value']);
                                $list['tx_hash'] = md5($accname . $list['time'] . $list['value']);
                                if ($list['value'] == "") {
                                    continue;
                                }

                                $bank->tx_hash = $list['tx_hash'];
                                $bank->bank = 'kbank_' . $accname;
                                $bank->detail = $list['detail'];
                                $bank->save();

                                $newbank = $this->bankPaymentRepository->findWhere([
                                    'tx_hash' => $list['tx_hash'],
                                    'bank' => 'kbank_' . $accname,
                                    'detail' => $list['detail']
                                ]);

                                if ($newbank->doesntExist()) {
                                    $this->bankPaymentRepository->create([
                                        'bank' => 'kbank_' . $accname,
                                        'account_code' => $bank['code'],
                                        'bankstatus' => 1,
                                        'bankname' => 'KBANK',
                                        'detail' => $list['detail'],
                                        'status' => 0,
                                        'value' => $list['value'],
                                        'bank_time' => $list['date'],
                                        'channel' => $list['channel'],
                                        'tx_hash' => $list['tx_hash'],
                                        'atranferer' => $list['acc_num'],
                                        'create_by' => 'AUTO'
                                    ]);
                                    $total[] = $list['tx_hash'];
                                }

                            }

                            $next = $html->find("a[href*='action=detail']");
                            $totalPage = count($next);
                            if (!(empty($next))) {
                                $currentPage = 1;
                                foreach ($next as $a) {
                                    $dup++;
                                    $currentPage++;
                                    if ($currentPage < ($totalPage - 1)) {
                                        continue;
                                    }
                                    $total_next = array();

                                    $_query = strstr($a->href, '?');

                                    $response = Http::withOptions($option)->get('https://ebank.kasikornbankgroup.com/retail/cashmanagement/TodayAccountStatementInquiry.do' . $_query);
                                    if ($response->successful()) {
                                        $html = HtmlDomParser::str_get_html($response->body());
                                        $table = $html->find('table[rules="rows"]', 0);
                                        if (!(empty($table))) {
                                            foreach ($table->find('tr') as $tr) {
                                                $td1 = $config->clean($tr->find('td', 0)->plaintext);
                                                $pos = strpos($td1, $s);
                                                if ($pos !== false) {
                                                    continue;
                                                }

                                                $list = array();
                                                preg_match_all('/<td class=inner_table_.*?>\s?(.*?)<\/td>/', $tr, $temp2);
                                                foreach ($temp2[1] as $key => $val) {
                                                    switch ($key) {
                                                        case 0:
                                                            $val = str_replace('<br>', '', $val);
                                                            $n = preg_split('/\s+/', substr($val, 0, -3));
                                                            $ndate = explode("/", $n[0]);
                                                            $list['time'] = strtotime('20' . $ndate[2] . '-' . $ndate[1] . '-' . $ndate[0] . ' ' . $n[1]);
                                                            $list['date'] = '20' . $ndate[2] . '-' . $ndate[1] . '-' . $ndate[0] . ' ' . $n[1];
                                                            break;
                                                        case 1:
                                                            $list['channel'] = $val;
                                                            break;
                                                        case 2:
                                                            $list['detail'] = $val;
                                                            break;
                                                        case 3:
                                                            if ($val != '') {
                                                                $list['value'] = "-" . floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                                            }
                                                            break;
                                                        case 4:
                                                            if ($val != '') {
                                                                $list['value'] = floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                                            }
                                                            break;
                                                        case 5:
                                                            $list['fee'] = floatval(preg_replace('/[^0-9\.\+\-]/', '', $val));
                                                            break;
                                                        case 6:
                                                            $list['acc_num'] = str_replace(array('x', '-'), array('*', ''), $val);
                                                            break;
                                                        case 7:
                                                            $list['detail'] .= ' (' . $val . ')';
                                                            break;
                                                    }
                                                }
                                                $list['value'] = str_replace(",", "", $list['value']);
                                                $list['tx_hash'] = md5($accname . $list['time'] . $list['value']);
                                                if ($list['value'] == "") {
                                                    continue;
                                                }


                                                $newbank = $this->bankPaymentRepository->findWhere([
                                                    'tx_hash' => $list['tx_hash'],
                                                    'bank' => 'kbank_' . $accname,
                                                    'detail' => $list['detail']
                                                ]);

                                                if ($newbank->doesntExist()) {
                                                    $this->bankPaymentRepository->create([
                                                        'bank' => 'kbank_' . $accname,
                                                        'account_code' => $bank['code'],
                                                        'bankstatus' => 1,
                                                        'bankname' => 'KBANK',
                                                        'detail' => $list['detail'],
                                                        'status' => 0,
                                                        'value' => $list['value'],
                                                        'bank_time' => $list['date'],
                                                        'channel' => $list['channel'],
                                                        'tx_hash' => $list['tx_hash'],
                                                        'atranferer' => $list['acc_num'],
                                                        'create_by' => 'AUTO'
                                                    ]);
                                                    $total[] = $list['tx_hash'];
                                                } else {
                                                    $row = $newbank->count();
                                                    $total[] = $list['tx_hash'];
                                                    $dp = array_count_values($total);
                                                    for ($d = $row; $d < $dp[$list['tx_hash']]; $d++) {
                                                        $this->bankPaymentRepository->create([
                                                            'bank' => 'kbank_' . $accname,
                                                            'account_code' => $bank['code'],
                                                            'bankstatus' => 1,
                                                            'bankname' => 'KBANK',
                                                            'detail' => $list['detail'],
                                                            'status' => 0,
                                                            'value' => $list['value'],
                                                            'bank_time' => $list['date'],
                                                            'channel' => $list['channel'],
                                                            'tx_hash' => $list['tx_hash'],
                                                            'atranferer' => $list['acc_num'],
                                                            'create_by' => 'AUTO'
                                                        ]);
                                                    }
                                                }
                                            }
                                        }
                                        $bank->job_process = 'success';
                                        $bank->job_detail = 'บันทึกข้อมูลแล้ว';
                                        $bank->job_date = $datenow;
                                        $bank->save();
                                    }
                                }
                            }
                        }
                    }
                    $bank->job_process = 'error';
                    $bank->job_detail = 'เชื่อมต่อ TodayAccountStatementInquiry ผิดพลาด';
                    $bank->job_date = $datenow;
                    $bank->save();
                }
                $bank->job_process = 'error';
                $bank->job_detail = 'เชื่อมต่อ TodayAccountStatementInquiry ผิดพลาด';
                $bank->job_date = $datenow;
                $bank->save();
            }
            $bank->job_process = 'error';
            $bank->job_detail = 'เชื่อมต่อ RetailWelcome ผิดพลาด';
            $bank->job_date = $datenow;
            $bank->save();
        }
    }
}
