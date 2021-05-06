<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropIndexBankPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dropIndex('id'); // Drops index 'geo_state_index'
            $table->dropIndex('report_id'); // Drops index 'geo_state_index'
            $table->dropIndex('tx_hash'); // Drops index 'geo_state_index'
            $table->dropIndex('bank_index'); // Drops index 'geo_state_index'

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
