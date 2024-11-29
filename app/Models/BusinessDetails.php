<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessDetails extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function activation(){
        return $this->hasMany(ActivationCodeLog::class, 'business_detail_id');
    }
}
