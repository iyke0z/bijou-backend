<?php
namespace App\Traits;

use App\Models\BusinessDetails;

trait BusinessTrait{
    public static function get_vat(){
        $vat = BusinessDetails::first();
        return $vat->vat;
    }
    public static function get_details(){
        $details = BusinessDetails::with('activation')->first();
        return $details;
    }

    public static function expire(){
        $expire = BusinessDetails::first();
        $expire->status = 'inactive';
        $expire->save();
        return res_completed('app has expired');
    }

    public static function restore(){
        $restore = BusinessDetails::first();
        $restore->status = 'active';
        $restore->expiry_date = strtotime( "+1 month", time() );
        $restore->save();
        return res_completed('app restored');
    }
}
