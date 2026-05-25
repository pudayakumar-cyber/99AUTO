<?php

namespace App\Models;
use DB;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'user_info',
        'cart',
        'shipping',
        'discount',
        'payment_method',
        'txnid',
        'charge_id',
        'transaction_number',
        'order_status',
        'payment_status',
        'shipping_info',
        'billing_info',
        'currency_sign',
        'currency_value',
        'tax',
        'state_price',
        'state',
        'tracking_number',
        'shipment_provider',
        'shipping_method_code',
        'shipping_method_name',
        'shipping_carrier',
        'shipment_meta',
    ];

    public function user()
    {
    	return $this->belongsTo('App\Models\User')->withDefault();
    }

    public function tracks()
    {
    	return $this->belongsTo('App\Models\TrackOrder','order_id')->withDefault();
    }

    public function tranaction()
    {
    	return $this->hasOne('App\Models\Transaction','order_id')->withDefault();
    }

    public function tracks_data()
    {
    	return $this->hasMany('App\Models\TrackOrder','order_id');
    }

    public function notificaton()
    {
    	return $this->hasMany('App\Models\Notification','order_id');
    }

}
