<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'tenant_number',
    'contract_number',
    'property_unit',
    'due_date',
    'monthly_target',
    'collector_id',
    'owner_id',
    'supervisor_id',
    'national_id',
    'birth_date',
    'notes',
    'status',
])]
class TenantInfo extends Model
{
    use HasFactory;

    protected $table = 'tenant_info';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'birth_date' => 'date',
            'monthly_target' => 'decimal:2',
        ];
    }

    /**
     * The underlying user account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The collector assigned to this tenant.
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * The owner linked to this tenant/unit (optional).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The supervisor managing this tenant.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Notes recorded for this tenant.
     */
    public function notesList(): HasMany
    {
        return $this->hasMany(TenantNote::class, 'tenant_id')->latest();
    }

    /**
     * Payment promises / appointments for this tenant.
     */
    public function promises(): HasMany
    {
        return $this->hasMany(TenantPromise::class, 'tenant_id')->latest('promise_date');
    }

    /**
     * Payment ledger entries for this tenant.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'tenant_id')->latest('payment_date');
    }

    /**
     * Notice requests recorded for this tenant.
     */
    public function noticeRequests(): HasMany
    {
        return $this->hasMany(NoticeRequest::class, 'tenant_id')->latest();
    }

    /**
     * Calculate total paid amount for a specific period (e.g. 2026-10).
     */
    public function paidForPeriod(string $period): float
    {
        return (float) $this->payments()
            ->where('period', $period)
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value])
            ->sum('amount');
    }
}
