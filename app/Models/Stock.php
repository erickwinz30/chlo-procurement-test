<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_name',
        'specification',
        'category',
        'quantity',
        'unit',
        'min_stock',
        'max_stock',
        'last_purchase_price',
        'location',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity === 0;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
