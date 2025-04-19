<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionTypeToSplitPayments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('split_payments', function (Blueprint $table) {
            $table->enum('transaction_type', ['sales', 'purchases', 'expenditures'])->default('sales')->after('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('split_payments', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
}
