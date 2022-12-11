<?php

namespace Database\Seeders;

use App\Models\BusinessDetails;
use Illuminate\Database\Seeder;

class BusinessDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {


        $data = [
          "name" => "Edzain Enterprises",
          "logo" => null,
          "email" => "edzain@gmail.com",
          "website" => "www.edzain.com",
          "phone_one" => "+2348147082856",
          "phone_two" => null,
          "motto" =>"we want to serve you better",
          "vat" => 7.5,
          "status" => "active",
          "expiry_date" => now()

        ];
        BusinessDetails::create($data);
    }
}
