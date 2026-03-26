<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'contact_person',
        'tax_id',
        'status',
        'notes',
    ];

    public function procurementOrders(): HasMany
    {
        return $this->hasMany(ProcurementOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
