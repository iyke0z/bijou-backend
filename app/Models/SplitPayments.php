<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SplitPayments extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function transaction(){
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function bank(){
        return $this->belongsTo(Banks::class, 'bank_id');
    }

}
