<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccrualOptionsToExpenditures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expenditures', function (Blueprint $table) {
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->string('payment_type')->nullable(); //prepayment_postpayment
            $table->float('monthly_value', 11,2)->default(0);
            $table->integer('posting_day')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('expenditures', function (Blueprint $table) {
            $table->dropColumn('start_date');
            $table->dropColumn('end_date'); 
            $table->dropColumn('payment_type');
            $table->dropColumn('monthly_value');
            $table->dropColumn('posting_day');
        });
    }
}
