<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function getPackages(){
        $packages = Package::all();

        return res_success('', $packages);
    }
    public function createPackage(Request $request){
        Package::create([
            "price" => $request['price'],
            "duration" => $request['duration']
        ]);

        return res_completed('completed');
    }
    public function updatePackage(Request $request, $id){
        $package = Package::where('id', $id)->first();

        $package->update([
            "price" => $request['price'],
            "duration" => $request['duration']
        ]);

        return res_completed('update');
    }
    public function deletePackage($id){
        $package = Package::where('id', $id)->first();
        $package->delete();
        
        return res_completed('delete');

    }
}
