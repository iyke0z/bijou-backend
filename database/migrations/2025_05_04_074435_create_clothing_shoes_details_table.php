<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClothingShoesDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clothing_shoes_details', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->longText('sizes');
            $table->longText('colors');
            $table->string('material')->nullable();
            $table->longText('style')->nullable();
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
        Schema::dropIfExists('clothing_shoes_details');
    }
}
