<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSeo extends Model
{
    protected $table = 'page_seos';

    protected $fillable = [
        'page_name',
        'display_name',
        'title',
        'meta_keywords',
        'meta_description'
    ];
}
