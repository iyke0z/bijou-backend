<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionTypeToLiquidity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('liquidities', function (Blueprint $table) {
            $table->enum('transaction_type', ["CREDIT", "DEBIT"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('liquidities', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
}
