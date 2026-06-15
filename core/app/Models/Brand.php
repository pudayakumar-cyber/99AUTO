<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name', 'slug','photo', 'status','is_popular','meta_keywords','meta_descriptions'];
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany('App\Models\Item');
    }

}
