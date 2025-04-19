<?php

namespace App\Repositories;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\BusinessDetails;
use App\Models\BusinessTime;
use App\Models\LoginLog;
use App\Models\Shop;
use App\Models\ShopAccess;
use App\Models\User;
use App\Models\WaiterCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthRepository implements AuthRepositoryInterface{
    public function login($request){
        $user = User::with('shop_access')->with('shop')->where('phone', $request['phone'])->first();
        if(! $user || ! Hash::check($request['password'], $user->password)){
            return res_completed('The provided credentials are incorrect');
        }else{
            // log
            $log = new LoginLog;
            $log->user_id = $user->id;
            $log->action = 'login';
            $log->save();
            $token = $user->createToken('auth_token')->plainTextToken;
            return res_success("token", [
                'user' => User::with('role')->where('id', $user->id)->first(),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'shop' => Shop::where('id', $user->shop_id)->first()
            ]);
        }
    }
    public function logout(){
        $authUser = Auth::user()->id;
        $log = new LoginLog;
        $log->user_id = $authUser;
        $log->action = 'logout';
        $log->save();
        $user = Auth::user()->tokens()->delete();
        if ($user) {
            return res_completed('User logged out successfully.');
        } else {
            return res_unauthorized('Something went wrong.');
        }
    }
    public function create_business_details($request){
        // upload image
        $logo = null;
        if(!is_null($request['logo'])){
            $logo = Str::slug($request['name'], '-').time().'.'.$request['logo']->extension();
            $request['logo']->move(public_path('images/logo'), $logo);
        }
        BusinessDetails::create([
            "name" => $request["name"],
            "logo" => $logo,
            "email" => $request["email"],
            "website" => $request["website"],
            "phone_one" => $request["phone_one"],
            "phone_two" => $request["phone_two"],
            "motto" => $request["motto"],
            "vat" => $request["vat"],
            "status"=>$request["status"],
            "expiry_date" => Carbon::now()->addMonths(2)->timestamp
        ]);

        // create shop
        Shop::create([
            "title" => "Main shop",
            "address" => "default address",
            "status" => "active",
            "contact_person" => "Default User",
            "phone_number" => $request["phone_one"]
        ]);

        ShopAccess::create([
            "shop_id" => 1,
            "user_id" => 1
        ]);
        return res_completed('created');
    }
    public function update_business_details($request, $id){
        $logo = null;
        if(!is_null($request['logo']) && !is_string($request['logo'])){
            $logo = Str::slug($request['name'], '-').time().'.'.$request['logo']->extension();
            $request['logo']->move(public_path('images/logo'), $logo);
        }
        $business_details = BusinessDetails::find($id);
        $business_details->update([
            "name" => $request["name"],
            "logo" => !is_string($request['logo']) ? $logo: $request['logo'],
            // "email" => $request["email"],
            "website" => $request["website"],
            "phone_one" => $request["phone_one"],
            "phone_two" => $request["phone_two"],
            "motto" => $request["motto"],
            "vat" => $request["vat"],
            "status"=>$request["status"],
            "expiry_date" => $request['expiry_date'],
            "is_negative_stock" => $request['is_negative_stock'],
            "owner_equity" => $request['owner_equity'],
        ]);
        if (isset($request['times']['start_time']) && isset($request['times']['closing_time'])) {
            
            $businesstime = BusinessTime::first();
            $businesstime->update([
                'start_time' => $request['times']['start_time'],
                'closing_time' => $request['times']['closing_time'],
            ]);
        }

        return res_completed('updated');

    }
    public function delete_business_details($id){
        BusinessDetails::findOrFail($id)->delete();
        return res_completed('deleted');
    }
    public function generateOTP()
    {
        $otp = mt_rand(10000, 99999);
        $otpCheck = WaiterCode::where('code', $otp)->first();

        if (!$otpCheck) {
            return $otp;
        }
        return $this->generateOTP();
    }
    public function generate_user_codes($request){
        $user = WaiterCode::where('user_id', $request['user_id'])->first();
        if($user){
            $user->delete();
        }
        WaiterCode::create([
            'user_id' => $request['user_id'],
            'code' => $this->generateOTP()
        ]);

        return res_completed('code generated');
    }
}
