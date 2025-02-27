<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\ShopAccess;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shopExists = Shop::where('title', "Main shop")->first();

        if (!$shopExists) {
            Shop::create([
                "title" => "Main shop",
                "contact_person" => "Manager",
                "phone_number" => "00000000",
                "address" => "update address",
                "status" => "active",
            ]);
        }

        $accessExists = ShopAccess::where('shop_id', 1)->where('user_id', 1)->first();
        if (!$accessExists) {
            ShopAccess::create([
                "shop_id" => 1,
                "user_id" => 1
            ]);
        }
        
       
    }
}
