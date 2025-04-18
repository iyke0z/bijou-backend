<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsDeliveryNote extends Model
{
    use HasFactory;

    protected $table = 'goods_delivery_notes'; // Adjust if table name differs

    protected $fillable = [
        'transaction_id',
        'date_left_warehouse',
        'delivery_details',
        'note',
        'proccessed_by'
    ];

    public function transaction(){
        return $this->belongsTo(SalesOrder::class, 'transaction_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'processed_by');
    }
}