<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingConsent extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'identity',
        'identity_hash',
        'status',
        'source',
        'consent_text',
        'consented_at',
        'revoked_at',
        'last_ip',
        'last_user_agent',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
