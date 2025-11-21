<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Contracts\LineMemberRegistrar;
use Gametech\LineOA\Contracts\MemberRegistrationResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ตัวอย่าง implementation ของ LineMemberRegistrar
 *
 * NOTE:
 * - โบ๊ทต้อง "ปรับการสร้าง member" ให้เรียก service/Model จริงของระบบหลัก
 * - ตรงจุดที่มี TODO: ให้ผูกกับโค้ดปัจจุบัน (ไม่ควร copy ไปใช้ดื้อ ๆ โดยไม่ปรับ)
 */
class DefaultLineMemberRegistrar implements LineMemberRegistrar
{
    /**
     * สมัครสมาชิกใหม่จากข้อมูลที่ได้จาก LINE
     *
     * @param array $data
     * @return MemberRegistrationResult
     */
    public function registerFromLineData(array $data): MemberRegistrationResult
    {
        $phone      = Arr::get($data, 'phone');
        $name       = Arr::get($data, 'name');
        $surname    = Arr::get($data, 'surname');
        $bankCode   = Arr::get($data, 'bank_code');
        $accountNo  = Arr::get($data, 'account_no');

        if (!$phone || !$name || !$surname) {
            return MemberRegistrationResult::failure('MISSING_REQUIRED_FIELDS');
        }

        // NOTE: ตรงนี้ควรใช้ service/Repository เดิมของระบบโบ๊ท
        // ผมใส่โครงตัวอย่างไว้ใน transaction ให้เฉย ๆ
        try {
            return DB::transaction(function () use ($phone, $name, $surname, $bankCode, $accountNo, $data) {

                // 1) เช็กซ้ำอีกครั้ง (กัน race condition)
                if ($this->isPhoneExistInMembers($phone)) {
                    return MemberRegistrationResult::failure('PHONE_ALREADY_EXISTS');
                }

                if ($bankCode && $accountNo && $this->isBankAccountExistInMembers($bankCode, $accountNo)) {
                    return MemberRegistrationResult::failure('BANK_ACCOUNT_ALREADY_EXISTS');
                }

                // 2) สร้าง username/password ตั้งต้น
                [$username, $password] = $this->generateUsernameAndPassword($phone);

                // 3) สร้าง member จริง
                // TODO: แก้ส่วนนี้ให้เรียก Model/Service จริงของระบบโบ๊ท
                //        โค้ดข้างล่างเป็นโครงตัวอย่างเท่านั้น

                /** @var \App\Models\Member $member */
                // $member = \App\Models\Member::create([
                //     'username'      => $username,
                //     'password'      => bcrypt($password),
                //     'tel'           => $phone,
                //     'name'          => $name,
                //     'surname'       => $surname,
                //     'bank_code'     => $bankCode,
                //     'bank_account'  => $accountNo,
                //     'regis_source'  => 'line_oa',
                //     // ฟิลด์อื่น ๆ ตามโครงจริง...
                // ]);

                // MOCK: จำลองว่ามี member id = 1
                // ลบทิ้งและแทนที่ด้วยโค้ดจริง
                $memberId = 1;

                $loginUrl = $this->getLoginUrl();

                return MemberRegistrationResult::success(
                    $memberId,
                    $username,
                    $password,
                    $loginUrl,
                    null
                );
            });
        } catch (\Throwable $e) {
            // Log error ไว้ debug
            report($e);

            return MemberRegistrationResult::failure('REGISTER_EXCEPTION: ' . $e->getMessage());
        }
    }

    /**
     * ตรวจว่าเบอร์นี้มีใน members หรือยัง
     *
     * NOTE: โบ๊ทต้องปรับให้เรียก table จริง
     */
    protected function isPhoneExistInMembers(string $phone): bool
    {
        // ตัวอย่าง:
        // return \App\Models\Member::where('tel', $phone)->exists();
        return false;
    }

    /**
     * ตรวจว่าเลขบัญชีนี้มีอยู่แล้วหรือยัง
     *
     * NOTE: โบ๊ทต้องปรับให้เรียก table จริง
     */
    protected function isBankAccountExistInMembers(?string $bankCode, string $accountNo): bool
    {
        if (!$bankCode) {
            return false;
        }

        // ตัวอย่าง:
        // return \App\Models\MemberBank::where('bank_code', $bankCode)
        //     ->where('account_no', $accountNo)
        //     ->exists();

        return false;
    }

    /**
     * generate username/password ตั้งต้น (ตัวอย่าง)
     *
     * แนะนำให้โบ๊ทเอา logic จริงจากระบบที่ใช้อยู่ตอนนี้มาใส่แทน
     */
    protected function generateUsernameAndPassword(string $phone): array
    {
        // ตัวอย่างง่าย ๆ: "g" + 4 หลักท้าย + random string
        $suffix   = substr($phone, -4);
        $username = 'g' . $suffix . Str::lower(Str::random(3));
        $password = Str::random(8);

        return [$username, $password];
    }

    /**
     * คืน URL หน้า login ของเว็บปัจจุบัน
     *
     * NOTE: ถ้าโบ๊ทมี helper/core()->loginUrl() อยู่แล้ว ให้เปลี่ยนมาใช้ตัวนั้น
     */
    protected function getLoginUrl(): string
    {
        // ตัวอย่าง: ใช้ route หรือ config
        // return route('customer.login');
        return url('/login');
    }
}
