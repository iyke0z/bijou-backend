<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPriviledges extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'priviledge_id'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function priviledge(){
        return $this->belongsTo(Priviledges::class);
    }
}
