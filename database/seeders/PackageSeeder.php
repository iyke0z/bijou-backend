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
                'price' => 20000,
                'duration' => 365,
                'description' => 'One year'
            ],
            [
                'price' => 3000,
                'duration' => 30,
                'description' => 'One month'
            ],
            [
                'price' => 8000,
                'duration' => 90,
                'description' => 'Three months'
            ],
            [
                'price' => 11000,
                'duration' => 90,
                'description' => 'Six months'
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
