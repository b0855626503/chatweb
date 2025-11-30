<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineTemplatesTable extends Migration
{
    public function up()
    {
        Schema::create('line_templates', function (Blueprint $table) {
            $table->bigIncrements('id');

            // หมวด / scope เช่น register, general, error
            $table->string('category', 50)->default('general')->index();

            // key เช่น register.ask_phone, register.error_phone_invalid
            $table->string('key', 100)->unique();
            $table->string('message_type', 10)->default('text')->index();

            // ข้อความ template (รองรับ {username}, {password}, {login_url}, ฯลฯ)
            $table->longText('message');

            // อธิบายว่าใช้ตรงไหนในระบบ (ให้ทีมงานเข้าใจเวลาแก้)
            $table->string('description')->nullable();

            // เปิด/ปิดการใช้งาน template นี้
            $table->boolean('is_active')->default(true)->index();

            // ใครเป็นคนแก้ล่าสุด (อ้างไป employees/code/id)
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('line_templates');
    }
}
