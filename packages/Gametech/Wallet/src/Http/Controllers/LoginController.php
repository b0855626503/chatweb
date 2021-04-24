<?php

namespace Gametech\Wallet\Http\Controllers;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;


class LoginController extends AppBaseController
{
    use AuthenticatesUsers;

    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $redirectTo = RouteServiceProvider::HOME;


    /**
     * Create a new Repository instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('customer')->only('logout');

        $this->_config = request('_config');
    }

    public function username(): string
    {
        return 'user_name';
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        $username = $request->input('user_name');
//        dd($request);

        $chk = app('Gametech\Member\Repositories\MemberRepository')->findOneByField($this->username(), $username);

        if (is_null($chk)) {
            return $this->sendFailedLoginResponse($request);
        }

        if (is_null($chk->password)) {
            $dataadd['password'] = Hash::make($chk->user_pass);
            app('Gametech\Member\Repositories\MemberRepository')->update($dataadd, $chk->code);
        }


        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }


        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function authenticated(Request $request, $user)
    {


        Auth::guard('customer')->logoutOtherDevices(request('password'));

        return redirect()->route('customer.home.index');

    }


    protected function sendFailedLoginResponse(Request $request)
    {
//        dd($request);
        session()->flash('error', 'ไม่สามารถเข้าสู่ระบบได้ โปรดตรวจสอบ');
        return redirect()->back();

    }

    /**
     * Display the resource.
     *
     * @return View
     */
    public function show()
    {
        if (Auth::guard('customer')->check()) {

            return redirect()->route('customer.home.index');

        } else {
            return view($this->_config['view']);
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $this->guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        Event::dispatch('customer.logout.after', $user);

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
    }


    protected function loggedOut(Request $request): RedirectResponse
    {

        return redirect()->route($this->_config['redirect']);

    }

    protected function guard()
    {
        return Auth::guard('customer');
    }

    public function store($id, Request $request)
    {
        if (!is_numeric($id)) {
            $id = null;
        }
        $banks = app('Gametech\Payment\Repositories\BankRepository')->findWhere(['enable' => 'Y', 'show_regis' => 'Y', ['code', '<>', 0]]);
        $refers = app('Gametech\Core\Repositories\ReferRepository')->findWhere(['enable' => 'Y', ['code', '<>', 0]]);
        return view($this->_config['view'], compact('banks', 'refers'))->with('id', $id);
    }

    public function register(Request $request)
    {
        $config = core()->getConfigData();

        $datenow = now()->toDateTimeString();
        $today = now()->toDateString();
        $ip = $request->ip();
//        $data = $request->input();

        $data = $request->all();
        $tel = Str::of($data['tel'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();

        $data['tel'] = $tel;
        $username = $data['user_name'];
        $acc_no = Str::of($data['acc_no'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
        $data['acc_no'] = $acc_no;
        $bank_code = $data['bank'];

        if ($config->freecredit_all === 'Y') {
            $freecredit = 'Y';
        } else {
            $freecredit = 'N';
        }

        if ($config->verify_open === 'Y') {
            $verify = 'N';
        } else {
            $verify = 'Y';
        }

        $data['user_name'] = strtolower($data['user_name']);

        $validator = Validator::make($data, [
            'acc_no' => [
                'required',
                'digits_between:1,14',
                Rule::unique('members', 'acc_no')->where(function ($query) use ($bank_code) {
                    return $query->where('bank_code', $bank_code);
                })
            ],
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'password' => 'required|min:6',
            'password_confirm' => 'min:6|same:password',
            'tel' => 'required|numeric|unique:members,tel',
            'user_name' => 'required|string|different:tel|unique:members,user_name|max:10|regex:/^[a-z][a-z0-9]*$/',
            'bank' => 'required|numeric',
            'lineid' => 'required|alpha_dash',
            'refer' => 'required|numeric',
            'g-recaptcha-response' => 'required'
        ]);

        if ($validator->fails()) {
            session()->flash('error', 'โปรดตรวจสอบข้อมูลให้ถูกต้องก่อนกด สมัครสมาชิก');
            return redirect()->back()->withErrors($validator)->withInput();

        }

//        $request->validate([
//            'acc_no' => 'required|digits_between:1,10|unique:members,acc_no',
//            'firstname' => 'required|string',
//            'lastname'  => 'required|string',
//            'password' => 'required|min:6',
//            'password_confirm' => 'min:6|same:password',
//            'tel'   => 'required|numeric|unique:members,user_name',
//            'bank'   => 'required|numeric',
//            'lineid'   => 'required|string',
//            'refer'   => 'required|numeric',
//            'g-recaptcha-response' => 'required'
//        ]);

        Event::dispatch('customer.register.before', $data);


        if (!isset($data['upline'])) {
            $upline = 0;
        } else {
            $upline = $data['upline'];
            unset($data['upline']);
        }

        $refer = $data['refer'];
        unset($data['refer']);

        $pass = $data['password'];
        $pass_confirm = $data['password_confirm'];
        unset($data['password_confirm']);
        unset($data['password']);

        $name = $data['firstname'] . ' ' . $data['lastname'];
        if (isset($data['promotion'])) {
            $pro = 'Y';
        } else {
            $pro = 'N';
        }

        $param = [
            'secret' => config('capcha.secret'),
            'response' => $data['g-recaptcha-response']
        ];


        $captcha_verify_url = "https://www.google.com/recaptcha/api/siteverify";

        $response = Http::asForm()->post($captcha_verify_url, $param);


        if ($response->failed()) {

            session()->flash('error', 'พบข้อผิดพลาดในการตรวจสอบ Captcha');
            return redirect()->back();

        } elseif ($response->successful()) {
            $response = $response->json();
//            dd($response);
            if ($response['success'] != true) {
                session()->flash('error', 'คุณป้อน Captcha ไม่ถูกต้อง');
                return redirect()->back();
            }
        }
        unset($data['g-recaptcha-response']);


        unset($data['bank']);
        if ($bank_code == 4) {
            $acc_check = substr($acc_no, -4);
        } else {
            $acc_check = substr($acc_no, -6);
        }
        $acc_bay = substr($acc_no, -7);


        $data = array_merge($data, [
            'password' => Hash::make($pass),
            'refer_code' => $refer,
            'upline_code' => $upline,
            'user_name' => $username,
            'user_pass' => $pass,
            'tel' => $tel,
            'acc_no' => $acc_no,
            'acc_check' => $acc_check,
            'acc_bay' => $acc_bay,
            'acc_kbank' => '',
            'bank_code' => $bank_code,
            'confirm' => $verify,
            'freecredit' => $freecredit,
            'check_status' => 'N',
            'promotion' => $pro,
            'name' => $name,
            'user_create' => $name,
            'user_update' => $name,
            'lastlogin' => $datenow,
            'date_regis' => $today,
            'birth_day' => $today,
            'session_limit' => null,
            'payment_limit' => null,
            'payment_delay' => null,
            'remark' => '',
            'gender' => 'M',
            'ip' => $ip
        ]);

//        dd($data);


//        $validator = Validator::make($data, [
//            'acc_no' => 'required|digits_between:1,10|unique:members,acc_no',
//            'firstname' => 'required|string',
//            'lastname'  => 'required|string',
//            'user_name'      => 'required|unique:members,user_name',
//            'user_pass'   => 'confirmed|min:6|required',
//            'tel'   => 'required|numeric|unique:members,tel',
//            'bank_code'   => 'required|numeric',
//            'lineid'   => 'required|string',
//            'refer_code'   => 'required|numeric',
//        ]);
//        if ($validator->fails()) {
//            $errors = $validator->errors();
//            session()->flash('error', $errors->messages());
//            return redirect()->back();
//
//        }


        $response = app('Gametech\Member\Repositories\MemberRepository')->create($data);

        Event::dispatch('customer.register.after', $response);

        if (!$response->code) {
            session()->flash('error', 'พบข้อผิดพลาด ไม่สามารถบันทึกบ้อมูลได้');
            return redirect()->back();
        }

        if ($config->verify_open === 'N') {

            $games = app('Gametech\Game\Repositories\GameRepository')->findWhere(['auto_open' => 'Y', 'status_open' => 'Y']);

            foreach ($games as $i => $game) {
                app('Gametech\Game\Repositories\GameUserRepository')->addGameUser($game->code, $response->code, $data);
            }

            session()->flash('success', 'สมัครสมาชิกสำเร็จแล้ว สามารถเข้าระบบได้เลย');
            return redirect()->intended(route($this->_config['redirect']));

        } else {

            session()->flash('success', 'ขณะนี้ข้อมูลการสมัครของท่าน อยู่ในกระบวนการตรวจสอบโดยทีมงาน เมื่อทีมงานดพเนินการเสร็จ ท่านสมาชิกจะสามารถเข้าสู่ระบบของเวบไซต์ได้');
            return redirect()->intended(route($this->_config['redirect']));

        }

    }

    public function download()
    {
        $games = $this->loadGame();

        return view($this->_config['view'], compact('games'));
    }

    public function loadGame(): array
    {
        $responses = [];

        $results = collect(app('Gametech\Game\Repositories\GameRepository')->getGameUserById($this->id(), false)->toArray());


        foreach ($results as $i => $result) {
            $responses[strtolower($result['game_type'])][$i] = $result;
            $responses[strtolower($result['game_type'])][$i]['image'] = Storage::url('game_img/' . $result['filepic']);
        }

        return $responses;

    }

    public function checkAcc(Request $request)
    {
        $bankid = $request->input('bankid');
        $accno = $request->input('accno');
        $bank_code = 1;
        $firstname = '';
        $lastname = '';

        $data['acc_no'] = $accno;


        switch ($bankid) {
            case '2':
                $bank_code = 1;
                break;
            case '1':
                $bank_code = 2;
                break;
            case '3':
                $bank_code = 3;
                break;
            case '10':
                $bank_code = 4;
                break;
            case '4':
                $bank_code = 5;
                break;
            case '11':
                $bank_code = 6;
                break;
            case '15':
                $bank_code = 7;
                break;
            case '6':
                $bank_code = 8;
                break;
            case '7':
                $bank_code = 9;
                break;
            case '9':
                $bank_code = 10;
                break;
            case '12':
                $bank_code = 11;
                break;
            case '13':
                $bank_code = 13;
                break;
            case '14':
                $bank_code = 14;
                break;
            case '5':
                $bank_code = 15;
                break;
            case '8':
                $bank_code = 16;
                break;
            case '17':
                $bank_code = 17;
                break;

            default:
                $ret['status'] = 0;
                return $this->sendResponseNew($ret, 'Complete');
                break;
        }

        $param = [
            'destinationbankid' => $bank_code,
            'destinationaccnumber' => $accno
        ];

        $response = Http::asForm()->post('https://ks.pg-game888.com/web/api_ckaccname/sbo', $param);
        if ($response->successful()) {
            $response = $response->json();
            if ($response['status'] == 1000) {

                $validator = Validator::make($data, [
                    'acc_no' => [
                        'required',
                        'digits_between:1,15',
                        Rule::unique('members', 'acc_no')->where(function ($query) use ($bankid) {
                            return $query->where('bank_code', $bankid);
                        })
                    ],
                ]);


                $name = $response['name'];
                $name = explode(' ', $name);
                if (count($name) == 2) {

//                    $firstname = Str::of($name[0])->after('นาย');
                    $firstname = Str::of($name[0])->after('นาย')->after('นาง')->after('นายสาว')->after('น.ส.')->__toString();
                    $lastname = $name[1];
                } elseif (count($name) == 3) {
                    $firstname = $name[1];
                    $lastname = $name[2];
                } elseif (count($name) == 4) {
                    $firstname = $name[1];
                    $lastname = $name[3];
                }

                $response['firstname'] = $firstname;
                $response['lastname'] = $lastname;

                if ($validator->fails()) {
                    $response['status'] = 0;
                    return $this->sendResponseNew($response, 'มีเลขที่บัญชีนี้ในระบบแล้ว');
                }

                return $this->sendResponseNew($response, 'Complete');
            } else {
                $ret['status'] = 0;
                return $this->sendResponseNew($ret, 'ไม่พบข้อมูลของเลขที่บัญชีนี้');
            }
        }
    }
}
