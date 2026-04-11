<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'total_records',
        'processed_records',
        'progress',
        'status'
    ];
}
