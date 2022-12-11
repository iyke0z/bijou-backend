<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessDetails extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        "name",
        "logo",
        "email",
        "website",
        "phone_one",
        "phone_two",
        "motto",
        "vat",
        "status",
        "expiry_date"
    ];

    public function activation(){
        return $this->hasMany(ActivationCodeLog::class, 'business_detail_id');
    }
}
