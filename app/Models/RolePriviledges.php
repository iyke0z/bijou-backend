<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePriviledges extends Model
{
    use HasFactory;
    protected $fillable = [
        'role_id',
        'priviledge_id'
    ];

    public function role(){
        return $this->belongsTo(Roles::class);
    }

    public function priviledge(){
        return $this->belongsTo(Priviledges::class);
    }
}
