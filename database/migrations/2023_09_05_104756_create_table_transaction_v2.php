<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    // public function up()
    // {
    //     Schema::create('transaction_xendit', function (Blueprint $table) {
    //         $table->id();
    //         $table->string('payment_method_id')->nullable();
    //         $table->string('status')->nullable();
    //         $table->string('external_id')->nullable();
    //         $table->string('account_number')->nullable();
    //         $table->string('bank_code')->nullable();
    //         $table->integer('amount')->nullable();
    //         $table->date('transaction_timestamp')->nullable();
    //         $table->string('id_webhook')->nullable();
    //         $table->text('response')->nullable();
    //         $table->timestamps();
    //     });
    // }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_xendit');
    }
};
