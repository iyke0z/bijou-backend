<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\Priviledges;
use App\Models\RolePriviledges;
use App\Models\Roles;
use App\Models\User;
use App\Models\UserPriviledges;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface{
    use AuthTrait;
    public function create_user($request){
        // upload image
        $picture = null;
        if($request['picture'] != null){
            $picture = Str::slug($request['fullname'], '-').time().'.'.$request['picture']->extension();
            $request['picture']->move(public_path('images/users'), $picture);
        }
        $data = [
            "fullname"  => $request['fullname'],
            "email" => $request['email'],
            "phone" => $request['phone'],
            "address" => $request['address'],
            "role_id" => $request['role_id'],
            "password"=>Hash::make($request['password']),
            'dob'=>$request['dob'],
            'picture'=>$picture,
            'gender'=>$request['gender']
        ];

        $user = User::create($data);
        $user;
        $token = $user->createToken('auth_token')->plainTextToken;
        return res_success('success', [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function update_user($request, $id){
        $user = User::find($id);
        if($user->exists()){
            $picture = null;
            if(!is_null($request['picture']) && !is_string($request['picture'])){
                $picture = Str::slug($request['fullname'], '-').time().'.'.$request['picture']->extension();
                $request['picture']->move(public_path('images/users'), $picture);
            }
            $data = [
                "fullname"  => $request['fullname'],
                "email" => $request['email'],
                "phone" => $request['phone'],
                "address" => $request['address'],
                "role_id" => $request['role_id'],
                "password"=>Hash::make($request['password']),
                'dob'=>$request['dob'],
                'picture'=>!is_string($request['picture']) ? $picture: $request['picture'],
                'gender'=>$request['gender']
            ];
            $user->update($data);
            return res_completed('account updated successfully');
        }
        return res_not_found('account not found');
    }

    public function assign_user_priviledge($request, $id){
        $priviledge = $request['priviledges'];
        if(count($priviledge) > 0){
            for ($i=0; $i < count($priviledge); $i++) {
                $access = new UserPriviledges;
                $access->user_id = $id;
                $access->priviledge_id = $priviledge[$i];
                $access->save();
            }
        }
        return res_completed('priviledges assigned successfully');
    }
    public function assign_role_priviledge($request, $id){
        $priviledge = $request['priviledges'];
        if(count($priviledge) > 0){
            for ($i=0; $i < count($priviledge); $i++) {
                $access = new RolePriviledges;
                $access->role_id = $id;
                $access->priviledge_id = $priviledge[$i];
                $access->save();
            }
        }
        return res_completed('priviledges assigned successfully');
    }

    public function get_user($id){
        $user = User::with('role')
            ->with('purchase')
            ->with(['sales'=> function($q){
                $q->join('products', 'sales.product_id','products.id');}])
            ->with('access_log')
            ->with('expenditure_types')
            ->with('expenditure')->find($id);
        return res_success('user', $user);
    }

    public function delete_user($id){
        User::findOrFail($id)->delete();
        return res_completed('account deactivated');
    }

    public function create_role($request){
        $already_exists = [];
        for ($i=0; $i < count($request['name']) ; $i++) {
            $check = Roles::where('name', strtolower($request['name'][$i]))->first();
            if(!$check){
                $role = Roles::create(['name'=>strtolower($request["name"][$i])]);
                $role;
            }else{
                array_push($already_exists, $request['name'][$i]);
            }
        }
        if(count($already_exists) > 0){
            return res_success('already exists', $already_exists);
        }
        return res_completed('Roles Created');
    }

    public function delete_role($id)
    {
        $delete = Roles::findOrFail($id)->delete();
        if($delete){
            return res_completed('deleted');
        }
        return res_completed('something went wrong');
    }

    public function create_priviledge($request){
        $already_exists = [];
        for ($i=0; $i < count($request['name']) ; $i++) {
            $check = Priviledges::where('name', strtolower($request['name'][$i]))->first();
            if(!$check){
                $priviledge = Priviledges::create(['name'=>strtolower($request['name'][$i])]);
                $priviledge;
            }else{
                array_push($already_exists, $request['name'][$i]);
            }
        }
        if(count($already_exists)> 0){
            return res_success('already exists', $already_exists);
        }
        return res_completed('created');
    }

    public function delete_priviledge ($id){
        $delete = Priviledges::findOrFail($id)->delete();
        if($delete){
            return res_completed('deleted');
        }
        return res_completed('something went wrong');
    }
}
