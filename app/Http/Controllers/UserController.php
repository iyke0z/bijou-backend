<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePriviledgeRequest;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\RolePriviledgeRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserPriviledgeRequest;
use Illuminate\Http\Request;
use App\Interfaces\UserRepositoryInterface;
use App\Models\FunctionalityPriviledge;
use App\Models\Priviledge;
use App\Models\Priviledges;
use App\Models\Role;
use App\Models\RolePriviledge;
use App\Models\Roles;
use App\Models\User;
use App\Traits\AuthTrait;

class UserController extends Controller
{
    public $userRepo;
    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function create_user(CreateUserRequest $request){
        $validated = $request->validated();
        return $this->userRepo->create_user($validated);
    }
    public function update_user(UpdateUserRequest $request, $id){
        $validated = $request->validated();
        return $this->userRepo->update_user($validated, $id);
    }

    public function assign_user_priviledge(UserPriviledgeRequest $request, $id){
        $validated = $request->validated();
        return $this->userRepo->assign_user_priviledge($validated, $id);
    }

    public function assign_role_priviledge(Request $request, $id){
        // $validated = $request->validated();
        return $this->userRepo->assign_role_priviledge($request->all(), $id);
    }

    public function get_role_priviledge($id){
        $shopId = request()->query('shop_id');
        $getRolePriviledges = RolePriviledge::with('priviledge')->where('role_id', $id)->get();

        return res_success('success', $getRolePriviledges);
    }

    public function delete_user($id){
        return $this->userRepo->delete_user($id);
    }

    public function get_user($id){
        return $this->userRepo->get_user($id);
    }

    public function create_role(Request $request){
        return $this->userRepo->create_role($request->all());
    }

    public function delete_role($id){
        return $this->userRepo->delete_role($id);
    }

    public function all_roles(){
        return Role::with('priviledges')->get();
    }

    public function all_priviledges(){
        return Priviledge::with('user_priviledges')
                ->with('role_priviledges')
                ->get();
    }

    public function create_priviledge(CreatePriviledgeRequest $request){
        $validated = $request->validated();
        return $this->userRepo->create_priviledge($validated);
    }
    
    public function delete_priviledge($id){
        return $this->userRepo->delete_priviledge($id);
    }

    public function all_users(Request $request)
    {
        $shopId = $request->query('shop_id');
    
        $users = applyShopFilter(
            User::with(['role', 'shop', 'shop_access', 'access_code']),
            $shopId
        )->get();
    
        return res_success('users', $users);       
    }
    

}
