<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expenditure extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];


    public function type(){
        return $this->belongsTo(ExpenditureType::class, 'expenditure_type_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
