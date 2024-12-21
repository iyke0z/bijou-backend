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
        DB::statement("ALTER TABLE `expenditure_types` MODIFY COLUMN `expenditure_type` ENUM('cogs', 'opex', 'capex') NOT NULL DEFAULT 'opex'");

        Schema::table('expenditure_types', function (Blueprint $table) {
            $table->float('useful_life')->nullable();
            $table->float('salvage_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `expenditure_types` MODIFY COLUMN `expenditure_type` ENUM('cogs', 'opex') NOT NULL DEFAULT 'opex'");

        Schema::table('expenditure_types', function (Blueprint $table) {
            $table->dropColumn('useful_life');
            $table->dropColumn('salvage_value');
        });
    }
}
