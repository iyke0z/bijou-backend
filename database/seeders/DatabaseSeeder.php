<?php

namespace Database\Seeders;

use App\Models\BusinessTime;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        // $this->call(Users::class);
        $this->call(RoleSeed::class);
        $this->call(PriviledgeSeeder::class);
        // $this->call(ShopSeeder::class);
        $this->call(RolesPriviledge::class);
        $this->call(CategorySeeder::class);
    //     $this->call(ProductsSeeder::class);
    //     $this->call(WaiterCodeSeeder::class);
        // $this->call(BusinessDetailsSeeder::class);
        // $this->call(PackageSeeder::class);
        // $this->call(BusinessTimeSeeder::class);
    }
}
