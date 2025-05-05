<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  [
                "Food",
                "Drinks",
                "Cocktail",
                "Mocktail",
                "clothing",
                "books",
                "shoes",
                "real_estate",
                "cars",
                "accessories"
        ];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
              "name" => $dat[$i]
            ];

            $categoryExists = Category::where('name', $dat[$i])->first();

            if ($categoryExists) {
                # code...
            }else{
                Category::create($name);
            }
        }
    }
}
