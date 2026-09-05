<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLifecycleProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'primary_vehicle_year' => 'integer',
        'last_purchase_at' => 'datetime',
        'next_maintenance_due_date' => 'date',
        'total_orders' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(CustomerLifecycleOrder::class);
    }
}
