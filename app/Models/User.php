<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

     public function role(){
        return $this->belongsTo(Roles::class, 'role_id');
     }

     public function purchase(){
        return $this->hasMany(Purchase::class);
     }

     public function sales(){
        return $this->hasMany(Sales::class);
     }

     public function user_priviledges(){
        return $this->hasMany(UserPriviledges::class);
     }

     public function access_log(){
        return $this->hasMany(LoginLog::class);
     }

     public function purchase_log(){
        return $this->hasMany(PurchaseLog::class);
     }

     public function product_log(){
        return $this->hasMany(ProductLog::class);
     }

     public function expenditure_types(){
        return $this->hasMany(ExpenditureType::class);
     }

     public function expenditure(){
        return $this->hasMany(Expenditure::class);
     }

     public function access_code(){
        return $this->hasOne(WaiterCode::class, 'user_id');
     }

     public function transactions(){
      return $this->hasMany(Transaction::class, 'user_id');
     }
}
