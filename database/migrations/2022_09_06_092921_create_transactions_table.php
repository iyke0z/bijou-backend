<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable()->default('full_payment');
            $table->float('amount', 11 ,2)->default(0);
            $table->integer('customer_id')->nullable();
            $table->enum('platform', ['online','offline'])->default('offline');
            $table->string('payment_method')->nullable(); //['cash', 'transfer', 'card', 'wallet', 'pos']
            $table->string('table_description')->nullable();
            $table->integer('bank_id')->nullable();
            $table->integer('user_id');
            $table->enum('status', ['pending', 'completed', 'cancelled']);
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
        Schema::dropIfExists('transactions');
    }
}
