<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Priviledges extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function user_priviledges(){
        return $this->hasMany(UserPriviledges::class);
    }
    public function role_priviledges(){
        return $this->hasMany(RolePriviledges::class);
    }
}
