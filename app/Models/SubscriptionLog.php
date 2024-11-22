<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['id'];

    public function package(){
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function business(){
        return $this->belongsTo(BusinessDetails::class, 'business_id');
    }
}
