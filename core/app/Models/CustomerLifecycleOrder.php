<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLifecycleOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'maintenance_due_date' => 'date',
        'purchased_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(CustomerLifecycleProfile::class, 'customer_lifecycle_profile_id');
    }
}
