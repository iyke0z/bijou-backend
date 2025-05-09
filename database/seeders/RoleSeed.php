<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  ['Super Admin', 'Accountant', 'Sales Admin', 'Expenditure Manager'];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
                "name" => $dat[$i],
                'shop_id' => 1
            ];
            $roleExist = Role::where('name', $dat[$i])->first();
            if (!$roleExist) {
                Role::create($name);
            }
        }



    }
}
