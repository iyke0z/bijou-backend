<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDiscount extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ["customer_id","discount_id"];

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function discount(){
        return $this->belongsTo(Discount::class);
    }
}
