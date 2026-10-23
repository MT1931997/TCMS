<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'owner_id',
    'location',
    'created_by',
])]
class Property extends Model
{
    use HasFactory;

    /**
     * The owner of this property.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The user / supervisor who created this property record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Units / tenants associated with this property.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(TenantInfo::class, 'property_id');
    }

    /**
     * All contracts for this property.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'property_id');
    }

    /**
     * Active contracts currently running.
     */
    public function activeContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'property_id')->where('status', 'active');
    }

    /**
     * Previous contracts (renewed or closed).
     */
    public function previousContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'property_id')->whereIn('status', ['renewed', 'closed']);
    }

    /**
     * Count of units with active contracts.
     */
    public function activeContractsCount(): int
    {
        if ($this->contracts()->exists()) {
            return $this->activeContracts()->count();
        }

        return $this->tenants()
            ->where(function ($q) {
                $q->whereNotNull('contract_number')
                    ->orWhereIn('status', ['regular', 'late', 'paid']);
            })
            ->count();
    }

    /**
     * Total contract value of all currently active contracts.
     */
    public function activeContractsTotalValue(): float
    {
        return (float) $this->activeContracts()->sum('total_amount');
    }

    /**
     * Sum of approved/completed payments collected for this property in a given period (Y-m).
     */
    public function collectedAmountForPeriod(?string $period = null): float
    {
        $period = $period ?? now()->format('Y-m');

        return (float) Payment::query()
            ->whereIn('tenant_id', $this->tenants()->select('id'))
            ->where('period', $period)
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value])
            ->sum('amount');
    }

    /**
     * Total collected amount across all currently active contracts.
     */
    public function collectedOnActiveContracts(): float
    {
        return (float) $this->activeContracts()
            ->get()
            ->sum(fn (Contract $contract) => $contract->collectedAmount());
    }

    /**
     * Sum of late / overdue amounts for units in this property.
     */
    public function overdueAmount(): float
    {
        if ($this->activeContracts()->exists()) {
            return (float) $this->activeContracts()
                ->get()
                ->sum(fn (Contract $contract) => $contract->overdueAmount());
        }

        $currentPeriod = now()->format('Y-m');

        return (float) $this->tenants()
            ->get()
            ->sum(function (TenantInfo $tenant) use ($currentPeriod) {
                $target = (float) $tenant->monthly_target;
                $paid = $tenant->paidForPeriod($currentPeriod);

                return max(0, $target - $paid);
            });
    }
}
