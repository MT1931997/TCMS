<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'property_id',
    'unit_number',
    'floor',
    'type',
    'status',
    'rent_price',
    'notes',
])]
class Unit extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'rent_price' => 'decimal:2',
        ];
    }

    /**
     * The property/building this unit belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * All contracts for this unit.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'unit_id');
    }

    /**
     * The active contract currently covering this unit.
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class, 'unit_id')->where('status', ContractStatus::Active);
    }

    /**
     * Financial dues associated with this unit.
     */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class, 'unit_id');
    }

    /**
     * Check if unit is currently occupied.
     */
    public function isOccupied(): bool
    {
        return $this->status === UnitStatus::Occupied;
    }
}
