<?php

namespace Database\Seeders;

use App\Models\ExpenditureType;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ExpenditureSeeders extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shops = Shop::all();
        foreach ($shops as $shop) {

            $exists = ExpenditureType::where("name", 'like', "%logistics%")->where('shop_id', $shop->id)->first();
            if ($exists) {
                ExpenditureType::create([
                    'name' => 'Logistics',
                    'expenditure_type' => 'opex',
                    'user_id' => 1,
                    'useful_life' => 0,
                    'shop_id' => $shop->id,
                ]);
            }
        
    }

    }
}
