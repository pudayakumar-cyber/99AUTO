<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartRecoveryLink extends Model
{
    protected $fillable = ['token_hash', 'items', 'expires_at'];

    protected $casts = ['items' => 'array', 'expires_at' => 'datetime'];
}
