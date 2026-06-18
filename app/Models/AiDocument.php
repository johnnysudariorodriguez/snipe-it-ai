<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiDocument extends Model
{
    use HasFactory;

    protected $table = 'ai_documents';

    protected $fillable = [
        'original_name',
        'filename',
        'path',
        'size',
        'status',
        'inserted_count',
        'external_id',
        'error_message',
        'created_by',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
