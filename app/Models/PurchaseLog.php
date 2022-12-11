<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseLog extends Model
{
    use HasFactory;
    protected $fillable = ['purchase_detail_id','action','old_price','new_price','old_stock','new_stock', 'user_id'];

    public function detail(){
        return $this->belongsTo(PurchaseDetails::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
