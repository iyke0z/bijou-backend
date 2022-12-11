<?php
namespace App\Traits;

use App\Models\Transaction;

trait RefCode{
    public static function gen_ref_code() {
        // getLast id from transaction table db
        $transaction = Transaction::orderBy('id', 'DESC')->get();
        $ref_id = count($transaction) < 1 ? 1 : $transaction[0]->id+1;
        return $ref_id;
    }
}
