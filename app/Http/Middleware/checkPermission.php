<?php

namespace App\Http\Middleware;

use App\Models\Priviledge as ModelsPriviledge;
use App\Models\Priviledges;
use App\Models\RolePriviledge;
use App\Models\RolePriviledges;
use App\Models\User;
use App\Models\UserPriviledge;
use App\Models\UserPriviledges;
use Closure;
use Database\Seeders\Priviledge;
use Illuminate\Http\Request;

class checkPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $priviledge)
    {
        //check if use has priviledges to access this function
        $userRole = User::find(auth()->id());
        $priviledge = ModelsPriviledge::where('name', $priviledge)->first();

        if ($userRole) {
            $user_role_can_access = RolePriviledge::where('role_id', $userRole->role_id)->where('priviledge_id', $priviledge->id)->first();
            $user_has_priviledge = UserPriviledge::where('users_id', auth()->id())->where('priviledges_id', $priviledge->id)->first();

            if ($user_has_priviledge || $user_role_can_access) {
                return $next($request);
            }else{
                return res_unauthorized('Unauthorized');
            }
        }

    }
}
