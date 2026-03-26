<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_id',
        'item_name',
        'specification',
        'category',
        'quantity',
        'unit',
        'estimated_price',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
