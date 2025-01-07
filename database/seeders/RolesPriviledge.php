<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RolePriviledge;
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
        $dat =  [
            ['role_id' => 1,
            'priviledge_id' => 1],
            ['role_id' => 1,
            'priviledge_id' => 2],
            ['role_id' => 1,
            'priviledge_id' => 3],
            ['role_id' => 1,
            'priviledge_id' => 4],
            ['role_id' => 1,
            'priviledge_id' => 5],
            ['role_id' => 1,
            'priviledge_id' => 6],
            ['role_id' => 1,
            'priviledge_id' => 7],
            ['role_id' => 1,
            'priviledge_id' => 8],
            ['role_id' => 1,
            'priviledge_id' => 9],
            ['role_id' => 1,
            'priviledge_id' => 10],
            ['role_id' => 1,
            'priviledge_id' => 11],
            ['role_id' => 1,
            'priviledge_id' => 12],
            ['role_id' => 1,
            'priviledge_id' => 13],
            ['role_id' => 1,
            'priviledge_id' => 14]
        ];

        foreach ($dat as $key => $value) {
            $rolePriviledgeExists = RolePriviledge::where('role_id', $value['role_id'])->where('priviledge_id', $value['priviledge_id'])
            ->first();

            if ($rolePriviledgeExists) {
                # code...
            }else{
                RolePriviledge::create($value);
                
            }
        }
    }
}
