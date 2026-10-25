<?php

namespace App\Models;

use App\Enums\DueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contract_id',
    'tenant_id',
    'unit_id',
    'due_date',
    'amount',
    'paid_amount',
    'period',
    'status',
    'notes',
])]
class Due extends Model
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
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => DueStatus::class,
        ];
    }

    /**
     * The contract this due belongs to.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * The tenant responsible for this due.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The unit associated with this due.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Payments linked to this due.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'due_id');
    }

    /**
     * Calculate remaining unpaid amount for this due.
     */
    public function remainingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    /**
     * Check if this due is past its due date and not fully paid.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->remainingAmount() > 0;
    }

    /**
     * Record a payment against this due and update its status.
     * Returns the remaining unallocated amount from the paid sum if any.
     */
    public function recordPayment(float $paid): float
    {
        $remaining = $this->remainingAmount();
        $allocation = min($paid, $remaining);

        $newPaidAmount = (float) $this->paid_amount + $allocation;
        $this->paid_amount = $newPaidAmount;

        if ($newPaidAmount >= (float) $this->amount) {
            $this->status = DueStatus::Paid;
        } elseif ($newPaidAmount > 0) {
            $this->status = DueStatus::Partial;
        }

        $this->save();

        return max(0, $paid - $allocation);
    }
}
