<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'fullname',
        'address',
        'phone',
        'email',
        'wallet_balance',
    ];

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function discounts(){
        return $this->hasMany(CustomerDiscount::class);
    }
}
