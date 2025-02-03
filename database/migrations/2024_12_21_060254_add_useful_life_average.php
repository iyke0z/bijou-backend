<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUsefulLifeAverage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('expenditure_types', function (Blueprint $table) {
            $table->float('useful_life', 11, 2)->nullable();
            $table->float('salvage_value', 11, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('expenditure_types', function (Blueprint $table) {
            $table->dropColumn('useful_life');
            $table->dropColumn('salvage_value');
        });
    }
}
