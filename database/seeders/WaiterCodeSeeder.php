<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaiterCode;

class WaiterCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        $dat =  [
            // BOTTLE SERVIES
            [
                'user_id' =>1,
                'code' => 2244
            ],
            [
                'user_id' =>2,
                'code' => 1318
            ],
            [
                'user_id' =>3,
                'code' => 1248
            ],
            [
                'user_id' =>4,
                'code' => 1100
            ],
            [
                'user_id' =>5,
                'code' => 3344
            ]
        ];

        for ($i=0; $i < count($dat) ; $i++) {
            $code = [
                'user_id' =>$dat[$i]['user_id'],
                'code' => $dat[$i]['code'],
            ];
            WaiterCode::create($code);
        }
    }
}
