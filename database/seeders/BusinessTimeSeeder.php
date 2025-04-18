<?php

namespace Database\Seeders;

use App\Models\BusinessTime;
use Illuminate\Database\Seeder;

class BusinessTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            "start_time" => "00:00",
            "closing_time" => "23:59",
        ];

        BusinessTime::create($data);
    }
}
