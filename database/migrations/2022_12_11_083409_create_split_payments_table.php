
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSplitPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('split_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction_id');
            $table->enum('payment_method', ['cash', 'transfer', 'card',]);
            $table->double('amount');
            $table->integer('bank_id')->nullable();
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
        Schema::dropIfExists('split_payments');
    }
}
