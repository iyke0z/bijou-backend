<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];


    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function purchases(){
        return $this->hasMany(PurchaseDetails::class, 'product_id');
    }

    public function sales(){
        return $this->hasMany(Sale::class, 'product_id');
    }

    public function product_log(){
        return $this->hasMany(ProductLog::class);
    }

    public function images(){
        return $this->hasMany(ProductImages::class, 'product_id');
    }

    public function transferHistory(){
        return $this->hasMany(StrockTransaction::class, 'product_id');
    }

    public function clothingShoesDetail(){
        return $this->hasMany(ClothingShoesDetail::class, 'product_id');
    }
    public function booksDetail(){
        return $this->hasMany(BooksDetail::class, 'product_id');
    }
    public function realEstateDetail(){
        return $this->hasMany(RealEstateDetail::class, 'product_id');
    }
    public function carsDetail(){
        return $this->hasMany(CarsDetail::class, 'product_id');
    }
}

