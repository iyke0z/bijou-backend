<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivationCode extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ["id"];

    public function package(){
        return $this->belongsTo(Package::class, 'package_id');
    }
}
