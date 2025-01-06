<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateShopRequest;
use App\Models\Shop;
use App\Models\ShopAccess;
use App\Models\ShopManager;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function index(){
        $shops = Shop::all();

        return res_success("success", $shops);
    }
    public function create(CreateShopRequest $request){
            $validated = $request->validated();
           
            $newShop = Shop::create([
                "title" => $validated["title"],
                "address" => $validated["address"],
                "status" => $validated["status"],
                "contact_person" => $validated["contact_person"],
                "phone_number" => $validated["phone_number"],
            ]);
            
            return res_success("success", $newShop);


    }
    public function update(CreateShopRequest $request, $id){
        $validated = $request->validated();
        $shopExists = Shop::find($id);

        if ($shopExists) {
            $newShop = $shopExists->update([
                "title" => $validated["title"],
                "address" => $validated["address"],
                "status" => $validated["status"],
                "contact_person" => $validated["contact_person"],
                "phone_number" => $validated["phone_number"],
            ]);
            
            return res_success("success", $newShop);
        }else{
            return res_not_found('shop not found');
        }
    }
    public function show($id){
        $record = Shop::find($id);

        if ($record) {
            return res_success("success", $record);
        }else{
            return res_not_found('record not found');

        }
    }
    public function delete($id){
        $record = Shop::find($id)->delete();

        if ($record) {
            return res_success("success", "");
        }else{
            return res_not_found('record not found');

        }
    }

    public function assign(Request $request, $id){
        $addShops = $request['shopsToAdd'];
        $removeShops = $request['shopsToRemove'];
        //
        if(count($addShops) > 0){
            for ($i=0; $i < count($addShops); $i++) {
                if (!ShopAccess::where('shop_id',$addShops[$i])->where('user_id', $id)->first()) {
                    $access = new ShopAccess();
                    $access->user_id = $id;
                    $access->shop_id = $addShops[$i];
                    $access->save();
                }
            }
        }

        if(count($removeShops) > 0){
            for ($i=0; $i < count($removeShops); $i++) {
                $exists = ShopAccess::where('shop_id',$removeShops[$i])->where('user_id', $id)->first();
                if ($exists) {
                    $exists->delete();
                }

            }
        }
        return res_completed('Assigned successfully');
    }
}
