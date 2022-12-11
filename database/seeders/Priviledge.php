<?php

namespace Database\Seeders;

use App\Models\Priviledges;
use Illuminate\Database\Seeder;

class Priviledge extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  ['can_delete_product'];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
              "name" => $dat[$i]
            ];
            Priviledges::create($name);
        }
    }
}
