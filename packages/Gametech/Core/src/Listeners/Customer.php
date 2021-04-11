<?php

namespace Gametech\Core\Listeners;

use Exception;
use Gametech\Member\Repositories\MemberLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Support\Facades\Auth;


class Customer
{
    private $memberRepository;

    private $memberLogRepository;

    public function __construct(
        MemberRepository $memberRepository,
        MemberLogRepository $memberLogRepository
    )
    {
        $this->memberRepository = $memberRepository;
        $this->memberLogRepository = $memberLogRepository;
    }

    public function login($user)
    {
        try {

            $this->memberRepository->update(['lastlogin' => now()], $user->code);

            $this->memberLogRepository->create([
                'member_code' => $user->code,
                'mode' => 'LOGIN',
                'menu' => 'login',
                'record' => $user->code,
                'remark' => 'Login success',
                'item_before' => '',
                'item' => serialize($user),
                'ip' => request()->ip(),
                'user_create' => $user->name
            ]);

//            broadcast(new \App\Events\RealTimeMessage('Boat เสด็จแล้ว'))->toOthers();

            } catch (Exception $e) {
                report($e);
            }

    }

    public function logout($user)
    {
        try {

            $this->memberLogRepository->create([
                'member_code' => $user->code,
                'mode' => 'LOGOUT',
                'menu' => 'logout',
                'record' => $user->code,
                'remark' => 'Logout success',
                'item_before' => '',
                'item' => serialize($user),
                'ip' => request()->ip(),
                'user_create' => $user->name
            ]);

        } catch (Exception $e) {
            report($e);
        }

    }

    public function register($user)
    {
        try {

            $this->memberLogRepository->create([
                'member_code' => $user->code,
                'mode' => 'REGISTER',
                'menu' => 'register',
                'record' => $user->code,
                'remark' => 'Register success',
                'item_before' => '',
                'item' => serialize($user),
                'ip' => request()->ip(),
                'user_create' => $user->name
            ]);

        } catch (Exception $e) {
            report($e);
        }

    }

    public function memberEvent($event){
        $user = Auth::guard('customer')->user();
        try {

            $this->memberLogRepository->create([
                'member_code' => $user->code,
                'mode' => 'EVENT',
                'menu' => 'member',
                'record' => $user->code,
                'remark' => $event,
                'item_before' => '',
                'item' => '',
                'ip' => request()->ip(),
                'user_create' => $user->name
            ]);

        } catch (Exception $e) {
            report($e);
        }
    }



}
