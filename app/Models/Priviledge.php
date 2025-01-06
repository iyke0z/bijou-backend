<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Priviledge extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function user_priviledges(){
        return $this->hasMany(UserPriviledge::class, 'priviledges_id');
    }
    public function role_priviledges(){
        return $this->hasMany(RolePriviledge::class, 'priviledge_id');
    }
}
