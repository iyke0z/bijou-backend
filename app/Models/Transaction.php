<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function sales(){
        return $this->hasMany(Sales::class, 'ref');
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bank(){
        return $this->belongsTo(Banks::class, 'bank_id');
    }

    public function split(){
        return $this->hasMany(SplitPayments::class, 'transaction_id');
    }

}
