<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralLedgersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('account_name')->nullable();
            $table->enum('transaction_type', ['credit', 'debit'])->nullable();
            $table->string('description')->nullable();
            $table->integer('transaction_id')->nullable();
            $table->double('amount', 11, 2)->nullable();
            // $table->double('current_balance', 11, 2)->nullable();
            $table->integer('shop_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('general_ledgers');
    }
}
