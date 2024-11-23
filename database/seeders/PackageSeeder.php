<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $package = [
            [
                'price' => 5000,
                'duration' => 30,
                'description' => 'ONE MONTH'
            ],
            [
                'price' => 25000,
                'duration' => 365,
                'description' => 'ONE YEAR'
            ],
            
            [
                'price' => 10000,
                'duration' => 90,
                'description' => 'THREE MONTHS'
            ],
            [
                'price' => 15000,
                'duration' => 90,
                'description' => 'SIX MONTHS'
            ],
        ];

        foreach ($package as $key => $value) {
            Package::create(
                [
                    'price' => $value['price'],
                    'duration' => $value['duration'],
                    'description' => $value['description']
                ],
            );
        }
    }
}
