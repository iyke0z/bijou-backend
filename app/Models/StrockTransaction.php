<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrockTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];


    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function userOne(){
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(){
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function shopOne(){
        return $this->belongsTo(Shop::class,'originating_shop');
    }

    public function shopTwo(){
        return $this->belongsTo(Shop::class,'destination_shop');
    }
}
