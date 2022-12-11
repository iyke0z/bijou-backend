<?php

namespace Database\Seeders;

use App\Models\RolePriviledges;
use Illuminate\Database\Seeder;

class RolesPriviledge extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  [];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
              "name" => $dat[$i]
            ];
            RolePriviledges::create($name);
        }
    }
}
