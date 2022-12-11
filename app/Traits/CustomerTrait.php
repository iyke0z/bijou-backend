<?php
namespace App\Traits;

use App\Models\Customer;

trait CustomerTrait{
    public static function sell_from_wallet($id, $total) {
        //reduce from wallet
        $customer = Customer::findOrFail($id);
        if($customer->exists()){
            // reduce the wallet balance
            $customer->wallet_balance = $customer->wallet_balance - $total;
            $customer->save();
        }

    }
}
