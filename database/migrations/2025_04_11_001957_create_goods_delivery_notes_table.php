<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoodsDeliveryNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction_id');
            $table->string('date_left_warehouse')->nullable();
            $table->string('delivery_details')->nullable();
            $table->text('note')->nullable();
            $table->integer('proccessed_by')->nullable();
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
        Schema::dropIfExists('goods_delivery_notes');
    }
}
