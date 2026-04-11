<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'imported_count',
        'skipped_count',
        'error_message',
    ];
}
