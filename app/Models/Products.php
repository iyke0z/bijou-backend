<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Products extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name',
    'category_id',
    'stock',
    'price',
    'out_of_stock',
    'code'
];

    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function purchases(){
        return $this->hasMany(PurchaseDetails::class, 'product_id');
    }

    public function sales(){
        return $this->hasMany(Sales::class, 'product_id');
    }

    public function product_log(){
        return $this->hasMany(ProductLog::class);
    }

    public function images(){
        return $this->hasMany(ProductImages::class, 'product_id');
    }
}

