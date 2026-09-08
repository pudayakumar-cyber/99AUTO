<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingConsentEvent extends Model
{
    protected $fillable = [
        'marketing_consent_id',
        'user_id',
        'channel',
        'identity',
        'identity_hash',
        'action',
        'source',
        'consent_text',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
