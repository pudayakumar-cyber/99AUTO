<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'photo',
        'status',
        'is_feature',
        'meta_keywords',
        'meta_descriptions',
        'serial',
        'package_length',
        'package_width',
        'package_height',
        'package_weight',
    ];
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany('App\Models\Item');
    }

    public function subcategory()
    {
        return $this->hasMany('App\Models\Subcategory')->where('status', 1);
    }

}
