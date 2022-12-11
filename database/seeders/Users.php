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
                    ["fullname"  => 'Muktar',
                    "email" => 'mk@gmail.com',
                    "phone" => '+2348147082856',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08'],

                    ["fullname"  => 'Waiter 1',
                    "email" => 'w1@gmail.com',
                    "phone" => '+1234567',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08'],
                    ["fullname"  => 'Waiter 2',
                    "email" => 'w2@gmail.com',
                    "phone" => '+12345678',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08'],
                    ["fullname"  => 'Waiter 3',
                    "email" => 'w3@gmail.com',
                    "phone" => '+123456789',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08'],
                    ["fullname"  => 'Waiter 4',
                    "email" => 'w4@gmail.com',
                    "phone" => '+1234567890',
                    "address" => 'abuja,nigeria',
                    "role_id" => '1',
                    "password"=>Hash::make('mk2323'),
                    "gender"=>'male',
                    "dob"=>'1997-13-08']
                ];

                for ($i=0; $i < count($dat) ; $i++) {
                    $users = [
                        "fullname"  => $dat[$i]['fullname'],
                    "email" => $dat[$i]['email'],
                    "phone" => $dat[$i]['phone'],
                    "address" =>$dat[$i]['address'],
                    "role_id" => $dat[$i]['role_id'],
                    "password"=>$dat[$i]['password'],
                    "gender"=>$dat[$i]['gender'],
                    "dob"=>$dat[$i]['dob']                        
                    ];
                    User::create($users);
                }
        
    }
}
