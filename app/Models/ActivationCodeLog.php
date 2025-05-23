<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationCodeLog extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function code(){
        return $this->belongsTo(ActivationCode::class);
    }

    public function business(){
        return $this->belongsTo(BusinessDetails::class, 'business_detail_id');
    }
}
