<?php

namespace App\Models;

use App\Enums\ClaimGenerationMode;
use App\Enums\ContractStatus;
use App\Enums\PaymentCycle;
use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contract_number',
    'property_id',
    'tenant_id',
    'unit_number',
    'start_date',
    'end_date',
    'total_amount',
    'payment_cycle',
    'installment_amount',
    'claim_generation_mode',
    'status',
    'previous_contract_id',
    'closed_at',
    'closure_reason',
    'notes',
    'created_by',
])]
class Contract extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'payment_cycle' => PaymentCycle::class,
            'claim_generation_mode' => ClaimGenerationMode::class,
            'status' => ContractStatus::class,
        ];
    }

    /**
     * The property this contract belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * The tenant associated with this contract.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The user / supervisor who created this contract.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * If renewed, the previous contract this was renewed from.
     */
    public function previousContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_contract_id');
    }

    /**
     * Renewals originating from this contract.
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'previous_contract_id');
    }

    /**
     * Total amount collected during this contract.
     */
    public function collectedAmount(): float
    {
        if (! $this->tenant_id) {
            return 0;
        }

        return (float) Payment::query()
            ->where('tenant_id', $this->tenant_id)
            ->whereBetween('payment_date', [$this->start_date->format('Y-m-d'), $this->end_date->format('Y-m-d')])
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value])
            ->sum('amount');
    }

    /**
     * Calculate overdue amount on this contract according to claim_generation_mode.
     */
    public function overdueAmount(): float
    {
        $mode = $this->claim_generation_mode instanceof ClaimGenerationMode
            ? $this->claim_generation_mode
            : ClaimGenerationMode::tryFrom((string) $this->claim_generation_mode);

        if ($mode === ClaimGenerationMode::None) {
            return 0;
        }

        $installment = (float) $this->installment_amount;
        $now = now();

        if ($mode === ClaimGenerationMode::CurrentMonthOnly) {
            // Overdue is based on current month's installment
            $currentPeriod = $now->format('Y-m');
            $paidThisMonth = (float) Payment::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('period', $currentPeriod)
                ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value])
                ->sum('amount');

            return max(0, $installment - $paidThisMonth);
        }

        if ($mode === ClaimGenerationMode::FromStartDate) {
            // If contract hasn't started yet, no claims yet
            if ($this->start_date->isFuture()) {
                return 0;
            }

            // Calculate number of cycles elapsed from start_date to now
            $cycle = $this->payment_cycle instanceof PaymentCycle
                ? $this->payment_cycle
                : PaymentCycle::tryFrom((string) $this->payment_cycle);

            $monthsInterval = match ($cycle) {
                PaymentCycle::Quarterly => 3,
                PaymentCycle::SemiAnnual => 6,
                PaymentCycle::Annual => 12,
                default => 1,
            };

            $start = Carbon::parse($this->start_date);
            $diffInMonths = $start->diffInMonths($now);
            $cyclesCount = max(1, (int) floor($diffInMonths / $monthsInterval) + 1);

            $expectedDue = $cyclesCount * $installment;
            // Cap at total contract amount
            $expectedDue = min($expectedDue, (float) $this->total_amount);

            $totalPaid = $this->collectedAmount();

            return max(0, $expectedDue - $totalPaid);
        }

        return 0;
    }
}
