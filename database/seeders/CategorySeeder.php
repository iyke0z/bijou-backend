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
        ];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
              "name" => $dat[$i]
            ];
            Category::create($name);
        }
    }
}
