<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function customer(){
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function deliveryNote(){
        return $this->hasOne(GoodsDeliveryNote::class,'transaction_id');
    }
    public function sales(){
        return $this->hasMany(Sale::class, 'ref');
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

    public function logistics(){
        return $this->hasMany(LogisticsAccount::class,'transaction_id');
    }

}
