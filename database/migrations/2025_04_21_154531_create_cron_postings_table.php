<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronPostingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cron_postings', function (Blueprint $table) {
            $table->id();
            $table->string('type'); //sales/ purchase// expenditure
            $table->string('transaction_id');
            $table->string('started_at');
            $table->string('ended_at');
            $table->string('status'); //pending/ completed/ failed
            $table->string('amount');
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
        Schema::dropIfExists('cron_postings');
    }
}
