<?php

namespace Gametech\Core\Listeners;



use Exception;
use Gametech\Admin\Repositories\AdminRepository;
use Gametech\Member\Repositories\MemberLogRepository;


class Admin
{

    private $adminRepository;

    private $memberLogRepository;

    public function __construct(
        AdminRepository $adminRepository,
        MemberLogRepository $memberLogRepository
    )
    {
        $this->adminRepository = $adminRepository;
        $this->memberLogRepository = $memberLogRepository;
    }

    public function login($user)
    {
        try {

            $this->adminRepository->update(['lastlogin' => now()], $user->code);



//            broadcast(new \App\Events\RealTimeMessage('Boat เสด็จแล้ว'))->toOthers();

        } catch (Exception $e) {
            report($e);
        }

    }




}
