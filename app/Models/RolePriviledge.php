<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePriviledge extends Model
{
    use HasFactory;
    protected $guarded = ['id'];


    public function role(){
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function priviledge(){
        return $this->belongsTo(Priviledge::class, 'priviledge_id');
    }
}
