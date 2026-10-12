<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'collector_id',
    'amount',
    'payment_date',
    'payment_method',
    'receipt_number',
    'attachment_path',
    'period',
    'status',
    'notes',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class Payment extends Model
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
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The tenant info associated with this payment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The collector who received this payment.
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * The supervisor/manager who reviewed (approved or rejected) this payment.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The comments/notes on this payment.
     */
    public function notesList(): HasMany
    {
        return $this->hasMany(PaymentNote::class, 'payment_id')->latest();
    }
}
