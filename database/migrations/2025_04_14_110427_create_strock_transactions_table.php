<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStrockTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('strock_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('originating_shop');
            $table->integer('destination_shop');
            $table->integer('product_id');
            $table->integer('qty');
            $table->integer('shop_one_user_id');   
            $table->integer('shop_two_user_id')->nullable();
            $table->integer('previous_stock');
            $table->integer('current_stock');
            $table->integer('previous_stock_two');
            $table->integer('current_stock_two');
            $table->enum('transaction_status', ['pending', 'completed', 'cancel', 'rejected'])->default('pending');
            $table->enum('transaction_method', ['transfer', 'return'])->default('transfer');
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
        Schema::dropIfExists('strock_transactions');
    }
}
