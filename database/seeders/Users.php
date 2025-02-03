<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Users extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dat =  [
                    ["fullname"  => 'Ikenna',
                    "email" => 'eddyiyke3@gmail.com',
                    "phone" => '+2348147082856',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "shop_id"=>'1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08'],
                ];

                for ($i=0; $i < count($dat) ; $i++) {
                    $users = [
                        "fullname"  => $dat[$i]['fullname'],
                    "email" => $dat[$i]['email'],
                    "phone" => $dat[$i]['phone'],
                    "address" =>$dat[$i]['address'],
                    "role_id" => $dat[$i]['role_id'],
                    "shop_id"=>$dat[$i]['shop_id'],
                    "password"=>$dat[$i]['password'],
                    "gender"=>$dat[$i]['gender'],
                    "dob"=>$dat[$i]['dob']
                    ];
                    User::create($users);
                }

    }
}
