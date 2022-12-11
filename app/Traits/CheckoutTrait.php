<?php
namespace App\Traits;

use App\Models\Transaction;

trait CheckoutTrait{
    public static function get_transactions($id) {
        $transactions = Transaction::where('customer_id', $id)
                        ->whereIn('type', ['sold', 'debit'])
                        ->sum('amount');
        $tranCount = Transaction::where('customer_id', $id)
                        ->whereIn('type', ['sold', 'debit'])
                        ->get();
        return [$transactions, count($tranCount)];
    }

    public static function verify_pay_on_delivery($id, $location_name){
        $locations = explode(',', env('PAY_ON_DELIVERY_LOCATION'));
        $counter = 0;
        $l = 0; $h= count($locations)- 1;
        sort($locations);

        while ($l <= $h) {
            $middle = ceil(($l + $h)/2);
            if($location_name == $locations[$middle]){
                // $isInLocation = 1;
                $h = $middle + 1;
                $amount = SELF::get_transactions($id);
                if($amount[0] >= 200000 && $amount[1] >= 5){
                    return true;
                }else{
                    return false;
                }
            }else if($location_name > $locations[$middle]){
                $l = $middle + 1;
            }
            else{
                $h = $middle - 1;
            }

            $counter++;
        }

    }

    public static function verify_on_credit($id){
        $amount = SELF::get_transactions($id);

        if($amount[0] >= 1000000 && $amount[1] >= 10){
            return true;
        }else{
            return false;
        }
    }
}
