<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseSupportingDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ["id"];

    public function purchase(){
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
