<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('purchase_detail_id');
            $table->enum('action',['purchase','update']);
            $table->float('old_price', 11, 2);
            $table->float('new_price', 11, 2)->nullable();
            $table->float('old_stock', 11, 2);
            $table->float('new_stock', 11, 2)->nullable();
            $table->integer('user_id');
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
        Schema::dropIfExists('purchase_logs');
    }
}
