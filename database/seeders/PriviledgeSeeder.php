<?php

namespace Database\Seeders;

use App\Models\Priviledge;
use Illuminate\Database\Seeder;

class PriviledgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  [
            "can_manage_users",
            "can_manage_roles_priviledges",
            "can_manage_categories",
            "can_manage_products",
            "can_manage_purchases",
            "can_manage_customers",
            "can_manage_discounts",
            "can_manage_sales",
            "can_manage_expenditure",
            "can_view_reports",
            "can_manage_banks",
            "can_manage_shops",
            "can_manage_payroll",
            "can_manage_vendors",
            "can_see_liquidity"
    ];

        for ($i=0; $i < count($dat) ; $i++) {
            $name = [
              "name" => $dat[$i]
            ];
            $priviledgeExists = Priviledge::where('name', $dat[$i])->first();
            if (!$priviledgeExists) {
                Priviledge::create($name);
            }
        }
    }
}