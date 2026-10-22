<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = ['title', 'code_name', 'discount','status','no_of_times','type',
        'expires_at', 'recipient_email', 'first_order_only'];
    protected $casts = ['expires_at' => 'datetime', 'first_order_only' => 'boolean'];
    public $timestamps = false;
}
