<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFacebookWebhookLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facebook_webhook_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // แนบกับ facebook_accounts.id
            $table->unsignedBigInteger('facebook_account_id')->nullable()->index();

            $table->string('event_type', 50)->nullable();
            $table->string('event_id', 100)->nullable();

            // ใช้เพื่อตาม trace ระหว่าง log
            $table->uuid('request_id')->index();

            // meta info
            $table->string('ip', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('headers')->nullable();

            // raw payload จาก Facebook
            $table->longText('body')->nullable();

            // สถานะ HTTP ที่เราตอบกลับ Facebook
            $table->integer('http_status')->nullable();

            // ประมวลผลสำเร็จหรือไม่
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            // เก็บ error message ถ้า fail
            $table->text('error_message')->nullable();

            $table->timestamps();

            // FK
            $table->foreign('facebook_account_id')
                ->references('id')
                ->on('facebook_accounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('facebook_webhook_logs');
    }
}
