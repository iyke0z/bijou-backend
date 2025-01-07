<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLiquiditiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('liquidities', function (Blueprint $table) {
            $table->id();
            $table->float('previous_balance', 11, 2)->default(0);
            $table->float('current_balance', 11, 2)->default(0);
            $table->float('transaction_amount', 11, 2)->default(0);
            $table->string('remark')->default("no remark");
            $table->string('transaction_reference')->default("no remark");
            $table->softDeletes();
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
        Schema::dropIfExists('liquidities');
    }
}
