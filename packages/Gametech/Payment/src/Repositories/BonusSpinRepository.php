<?php

namespace Gametech\Payment\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class BonusSpinRepository extends Repository
{
    private $memberRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;

        parent::__construct($app);
    }
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\BonusSpin';
    }

    public function SpinCreate(array $data): bool
    {
        $member_code = $data['member_code'];
        $credit_after = $data['credit_after'];
        $diamond = $data['diamond_balance'];
        $reward_type = $data['reward_type'];
        $amount = $data['amount'];

        DB::beginTransaction();
        try {
            Event::dispatch('customer.spin.before', $data);

            $member = $this->memberRepository->sharedLock()->find($member_code);


            $bill = $this->create($data);



            $member->balance = $credit_after;
            $member->diamond = $diamond;
            $member->save();

            Event::dispatch('customer.spin.after', $bill);
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        DB::commit();

        return true;
    }
}
