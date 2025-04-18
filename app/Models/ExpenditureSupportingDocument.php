<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenditureSupportingDocument extends Model
{
    use HasFactory;

    use HasFactory, SoftDeletes;

    protected $guarded = ["id"];

    public function expenditure(){
        return $this->belongsTo(Expenditure::class, 'expenditure_id');
    }
}
