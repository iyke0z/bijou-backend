<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function purchase_detail(){
        return $this->hasMany(PurchaseDetails::class);;
    }
    public function documents(){
        return $this->hasMany(PurchaseSupportingDocument::class, 'purchase_id');
    }



}
