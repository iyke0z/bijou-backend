<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banks extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    public function transaction(){
        return $this->hasMany(Transaction::class, 'bank_id');
    }
}
