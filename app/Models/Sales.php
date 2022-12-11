<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sales extends Model
{
    use HasFactory;
    protected $fillable = [
        "product_id",	"ref",	"price",	"qty",	"user_id",	"prep_status"
    ];

    public function product(){
        return $this->belongsTo(Products::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function transaction(){
        return $this->belongsTo(Transaction::class, 'ref');
    }
}
