<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountBalanceToId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('logistics_accounts', function (Blueprint $table) {
            $table->float('previous_balance', 11, 2)->default(0);
            $table->float('current_balance', 11, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('logistics_accounts', function (Blueprint $table) {
            $table->dropColumn('previous_balance');
            $table->dropColumn('current_balance');
        });
    }
}
