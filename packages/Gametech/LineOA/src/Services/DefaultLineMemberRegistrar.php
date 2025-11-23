<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Contracts\LineMemberRegistrar;
use Gametech\LineOA\Contracts\MemberRegistrationResult;
use Gametech\Marketing\Models\MarketingMember as Member;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultLineMemberRegistrar implements LineMemberRegistrar
{
    /**
     * สมัครสมาชิกจริงจากข้อมูลที่ได้จาก LINE
     *
     * data ที่มาจาก flow:
     * - phone        (string)  -> เบอร์โทร
     * - name         (string)  -> ชื่อ
     * - surname      (string)  -> นามสกุล
     * - bank_code    (string)  -> ใช้ค่าเดียวกับ members.bank_code
     * - account_no   (string)  -> ใช้กับ members.acc_no
     */
    public function registerFromLineData(array $data): MemberRegistrationResult
    {
        $phone = Arr::get($data, 'phone');
        $name = Arr::get($data, 'name');
        $surname = Arr::get($data, 'surname');
        $bankCode = Arr::get($data, 'bank_code');
        $accountNo = Arr::get($data, 'account_no');

        if (! $phone || ! $name || ! $surname || ! $bankCode || ! $accountNo) {
            return MemberRegistrationResult::failure('MISSING_REQUIRED_FIELDS');
        }

        // user_name = phone, password fixed = 123456
        $username = $phone;
        $plainPassword = $phone;

        try {
            return DB::transaction(function () use ($phone, $name, $surname, $bankCode, $accountNo, $username, $plainPassword) {

                // กัน race condition: ตรวจซ้ำอีกที
                if ($this->isPhoneExistInMembersOrBankAccount($phone)) {
                    return MemberRegistrationResult::failure('PHONE_ALREADY_EXISTS');
                }

                if ($this->isBankAccountExistInMembersOrBankAccount($bankCode, $accountNo)) {
                    return MemberRegistrationResult::failure('BANK_ACCOUNT_ALREADY_EXISTS');
                }

                $today = now()->toDateString();
                $datenow = now()->toDateTimeString();
                $ip = request()?->ip() ?? '0.0.0.0';

                // logic acc_check / acc_bay ตาม register() เดิม
                if ((string) $bankCode === '4') {
                    $acc_check = substr($accountNo, -4);
                } else {
                    $acc_check = substr($accountNo, -6);
                }
                $acc_bay = substr($accountNo, -7);
                $acc_kbank = '';

                $fullname = trim($name.' '.$surname);

                // ค่า default หลาย ๆ ตัวอิงจาก register() เดิม แต่ simple ลง
                $member = Member::create([
                    'user_name' => $username,
                    'user_pass' => $plainPassword,
                    'password' => Hash::make($plainPassword),

                    'wallet_id' => $phone,
                    'tel' => $phone,

                    'firstname' => $name,
                    'lastname' => $surname,
                    'name' => $fullname,

                    'bank_code' => $bankCode,
                    'acc_no' => $accountNo,
                    'acc_check' => $acc_check,
                    'acc_bay' => $acc_bay,
                    'acc_kbank' => $acc_kbank,

                    // ทำให้ถือว่ายืนยันแล้ว (ไม่ใช้ OTP ใน flow LINE)
                    'confirm' => 'Y',

                    // default ตามที่น่าจะปลอดภัย
                    'freecredit' => 'N',
                    'check_status' => 'N',
                    'promotion' => 'N',

                    'user_create' => $fullname,
                    'user_update' => $fullname,

                    'lastlogin' => $datenow,
                    'date_regis' => $today,
                    'birth_day' => $today,

                    'session_limit' => null,
                    'payment_limit' => null,
                    'payment_delay' => null,
                    'remark' => '',

                    'gender' => 'M',
                    'team_id' => null,
                    'campaign_id' => null,

                    'otp' => '',
                    'ip' => $ip,
                ]);

                $memberId = $member->code ?? $member->id; // แล้วแต่ model ของโบ๊ทใช้ pk อะไร

                $loginUrl = $this->getLoginUrl();

                return MemberRegistrationResult::success(
                    $memberId,
                    $username,
                    $plainPassword,
                    $loginUrl,
                    null
                );
            });
        } catch (\Throwable $e) {
            report($e);

            return MemberRegistrationResult::failure('REGISTER_EXCEPTION: '.$e->getMessage());
        }
    }

    /**
     * ใช้ rules เดียวกับ controller:
     * - tel unique ใน members
     * - tel ห้ามชนกับ banks_account.acc_no
     */
    protected function isPhoneExistInMembersOrBankAccount(string $phone): bool
    {
        // 1) members.tel
        $existsInMember = Member::where(function ($q) use ($phone) {
            $q->where('tel', $phone)
                ->orWhere('user_name', $phone);
        })->exists();

        if ($existsInMember) {
            return true;
        }

        // 2) banks_account.acc_no
        $exists = DB::table('banks_account')
            ->where('acc_no', $phone)
            ->exists();

        return $exists;
    }

    /**
     * ใช้ rules เดียวกับ controller:
     * - acc_no unique ใน members (ตาม bank_code)
     * - acc_no ห้ามชนกับ banks_account.acc_no
     */
    protected function isBankAccountExistInMembersOrBankAccount(?string $bankCode, string $accountNo): bool
    {
        if (! $bankCode) {
            return false;
        }

        // 1) ซ้ำใน members (acc_no + bank_code)
        $dupMember = Member::where('bank_code', $bankCode)
            ->where('acc_no', $accountNo)
            ->exists();

        if ($dupMember) {
            return true;
        }

        // 2) banks_account.acc_no
        $exists = DB::table('banks_account')
            ->where('acc_no', $accountNo)
            ->exists();

        return $exists;
    }

    /**
     * ถ้าภายหลังอยากเปลี่ยน logic user_name/password ตามเว็บหลัก
     * สามารถปรับตรงนี้ และใน registerFromLineData ได้
     */
    protected function generateUsernameAndPassword(string $phone): array
    {
        // ใช้ phone เป็น user_name ตรง ๆ ตาม requirement
        $username = $phone;
        $password = $phone;

        return [$username, $password];
    }

    protected function getLoginUrl(): string
    {
        // ถ้าเว็บใช้ route login อยู่แล้ว เปลี่ยนมาใช้ route() ได้
        if (function_exists('route')) {
            try {
                return route('customer.session.index');
            } catch (\Throwable $e) {
                // เผื่อไม่มี route ชื่อนี้
            }
        }

        return url('/login');
    }
}
