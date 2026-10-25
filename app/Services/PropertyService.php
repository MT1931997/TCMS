<?php

namespace App\Services;

use App\Enums\ClaimGenerationMode;
use App\Enums\ContractStatus;
use App\Enums\PaymentCycle;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Enums\UserType;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Property;
use App\Models\TenantInfo;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyService
{
    /**
     * Get properties list with statistics, search, owner filtering, and owners options.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     properties: LengthAwarePaginator|Collection,
     *     statistics: array{
     *         properties_count: int,
     *         active_contracts_count: int,
     *         subtitle: string
     *     },
     *     owners: array<int, array{id: int, name: string}>
     * }
     */
    public function getListing(array $filters = [], ?int $perPage = null): array
    {
        $query = Property::query()->with(['owner', 'tenants', 'contracts']);

        // Search by property name, location, or owner name
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by owner
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        $query->latest();

        // Calculate statistics
        $allPropertiesCount = Property::count();
        $totalActiveContractsCount = Contract::where('status', ContractStatus::Active->value)->count();

        // If no contract rows exist yet, fallback to tenant_info counts
        if ($totalActiveContractsCount === 0) {
            $totalActiveContractsCount = TenantInfo::whereNotNull('property_id')
                ->where(function ($q) {
                    $q->whereNotNull('contract_number')
                        ->orWhereIn('status', ['regular', 'late', 'paid']);
                })
                ->count();

            if ($totalActiveContractsCount === 0) {
                $totalActiveContractsCount = TenantInfo::where(function ($q) {
                    $q->whereNotNull('contract_number')
                        ->orWhereIn('status', ['regular', 'late', 'paid']);
                })->count();
            }
        }

        $properties = $perPage !== null && $perPage > 0
            ? $query->paginate($perPage)
            : $query->get();

        // Available owners for the "كل الملاك" dropdown filter
        $owners = User::query()
            ->where('type', UserType::Owner->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $owner) => [
                'id' => $owner->id,
                'name' => $owner->name,
            ])
            ->values()
            ->toArray();

        return [
            'properties' => $properties,
            'statistics' => [
                'properties_count' => $allPropertiesCount,
                'active_contracts_count' => $totalActiveContractsCount,
                'subtitle' => "{$allPropertiesCount} عقار . {$totalActiveContractsCount} عقد ساري - اضغط على أي عقار للدخول إليه وإدارة عقوده ووحداته",
            ],
            'owners' => $owners,
        ];
    }

    /**
     * Create a new property.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Property
    {
        return DB::transaction(function () use ($data, $creator) {
            return Property::create([
                'name' => $data['name'],
                'owner_id' => $data['owner_id'],
                'location' => $data['location'] ?? null,
                'created_by' => $creator->id,
            ]);
        });
    }

    /**
     * Update an existing property.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Property $property, array $data): Property
    {
        return DB::transaction(function () use ($property, $data) {
            $property->update(array_filter([
                'name' => $data['name'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'location' => array_key_exists('location', $data) ? $data['location'] : $property->location,
            ], fn ($val) => $val !== null));

            return $property->fresh(['owner', 'tenants', 'contracts']);
        });
    }

    /**
     * Delete a property and safely disassociate any units and contracts.
     */
    public function delete(Property $property): void
    {
        if ($property->contracts()->exists()) {
            throw ValidationException::withMessages([
                'property' => 'لا يمكن حذف عقار عليه عقود. أنهِ العقود أو انقلها الأول.',
            ]);
        }

        DB::transaction(function () use ($property) {
            TenantInfo::where('property_id', $property->id)->update(['property_id' => null]);
            $property->delete();
        });
    }

    /**
     * Get detailed view for a property ("فتح العقار"):
     * Includes top cards statistics, active contracts table,
     * tenants without active contract table, and previous contracts table.
     *
     * @return array<string, mixed>
     */
    public function getPropertyDetails(Property $property, ?string $period = null): array
    {
        $property->load(['owner', 'tenants.user']);
        $period = $period ?? now()->format('Y-m');

        // 1. Active Contracts
        $activeContracts = $property->contracts()
            ->where('status', ContractStatus::Active->value)
            ->with([
                'tenant.user',
                'tenant.payments' => fn ($q) => $q->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value]),
            ])
            ->latest('id')
            ->get();

        $activeContractsCount = $activeContracts->count();

        // Fallback if contracts table has no records for this property but tenants exist
        if ($activeContractsCount === 0 && $property->tenants()->exists()) {
            $activeContractsCount = $property->activeContractsCount();
        }

        // Contracts expiring in next 30 days
        $expiringCount = $activeContracts->filter(function (Contract $c) {
            return $c->end_date && $c->end_date->isFuture() && $c->end_date->diffInDays(now()) <= 30;
        })->count();

        $renewalNotice = $expiringCount > 0
            ? "{$expiringCount} عقد قارب على الانتهاء"
            : 'لا توجد عقود للتجديد';

        // Total active contract values
        $activeContractsTotalValue = (float) $activeContracts->sum('total_amount');

        // Total collected on active contracts
        $activeContractsCollected = (float) $activeContracts->sum(fn (Contract $c) => $c->collectedAmount());

        // Collected this month on active contracts / property units
        $thisMonthCollected = (float) Payment::query()
            ->whereIn('tenant_id', $property->tenants()->select('id'))
            ->where('period', $period)
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value])
            ->sum('amount');

        // Overdue amount
        $totalOverdue = (float) $activeContracts->sum(fn (Contract $c) => $c->overdueAmount());
        if ($activeContractsCount === 0 && $totalOverdue === 0.0) {
            $totalOverdue = $property->overdueAmount();
        }

        // 2. Tenants associated with property without an active contract ("مستأجرون مرتبطون بالعقار بدون عقد ساري")
        $activeTenantIds = $activeContracts->pluck('tenant_id')->filter()->unique()->toArray();
        $tenantsWithoutActiveContract = $property->tenants()
            ->whereNotIn('id', $activeTenantIds)
            ->with(['user'])
            ->get();

        // 3. Previous contracts log (renewed and closed) ("سجل العقود السابقة (المجددة والمغلقة)")
        $previousContracts = $property->contracts()
            ->whereIn('status', [ContractStatus::Renewed->value, ContractStatus::Closed->value])
            ->with([
                'tenant.user',
                'tenant.payments' => fn ($q) => $q->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Approved->value]),
            ])
            ->latest('id')
            ->get();

        return [
            'property' => $property,
            'statistics' => [
                'active_contracts_count' => $activeContractsCount,
                'renewal_notice' => $renewalNotice,
                'active_contracts_total_value' => $activeContractsTotalValue,
                'active_contracts_total_value_formatted' => number_format($activeContractsTotalValue, 0).' ر.س',
                'active_contracts_collected' => $activeContractsCollected,
                'active_contracts_collected_formatted' => number_format($activeContractsCollected, 0).' ر.س',
                'collected_this_month' => $thisMonthCollected,
                'collected_this_month_formatted' => 'هذا الشهر: '.number_format($thisMonthCollected, 0).' ر.س',
                'total_overdue' => $totalOverdue,
                'total_overdue_formatted' => number_format($totalOverdue, 0).' ر.س',
            ],
            'active_contracts' => $activeContracts,
            'tenants_without_active_contract' => $tenantsWithoutActiveContract,
            'previous_contracts' => $previousContracts,
        ];
    }

    /**
     * Get contract form options (payment cycles, claim generation modes, contract statuses, collectors, available tenants).
     *
     * @return array<string, mixed>
     */
    public function getContractOptions(Property $property): array
    {
        $collectors = User::query()
            ->where('type', UserType::Collector->value)
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->toArray();

        // Available tenants in the system for "مستأجر مسجل بالنظام"
        $tenants = TenantInfo::query()
            ->with(['user', 'property'])
            ->get()
            ->map(fn (TenantInfo $t) => [
                'id' => $t->id,
                'tenant_number' => $t->tenant_number,
                'name' => $t->user?->name ?? '',
                'phone' => $t->user?->phone ?? '',
                'national_id' => $t->national_id ?? $t->user?->national_id ?? '',
                'property_name' => $t->property?->name,
                'property_unit' => $t->property_unit,
                'display_label' => ($t->user?->name ?? 'مستأجر').' - '.($t->user?->phone ?? '').' ('.$t->property_unit.')',
            ])
            ->values()
            ->toArray();

        return [
            'payment_cycles' => PaymentCycle::options(),
            'claim_generation_modes' => ClaimGenerationMode::options(),
            'contract_statuses' => ContractStatus::options(),
            'collectors' => $collectors,
            'tenants' => $tenants,
        ];
    }

    /**
     * Create a new contract for a property (supports both existing tenant and new tenant).
     *
     * @param  array<string, mixed>  $data
     */
    public function createContract(Property $property, array $data, User $creator): Contract
    {
        if (! empty($data['unit_id'])) {
            $unit = Unit::findOrFail($data['unit_id']);
            $targetUnitNumber = $unit->unit_number;
            $targetUnitId = $unit->id;
        } else {
            $targetUnitNumber = $data['unit_number'];
            $targetUnitId = null;
        }

        $unitIsTaken = Contract::where('property_id', $property->id)
            ->where(function ($q) use ($targetUnitId, $targetUnitNumber) {
                if ($targetUnitId) {
                    $q->where('unit_id', $targetUnitId)->orWhere('unit_number', $targetUnitNumber);
                } else {
                    $q->where('unit_number', $targetUnitNumber);
                }
            })
            ->where('status', ContractStatus::Active)
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($unitIsTaken) {
            throw ValidationException::withMessages([
                'unit_number' => 'الوحدة عليها عقد سارٍ في نفس الفترة.',
            ]);
        }

        return DB::transaction(function () use ($property, $data, $creator, $targetUnitNumber, $targetUnitId) {
            $paymentCycle = PaymentCycle::from($data['payment_cycle']);
            $totalAmount = (float) $data['total_amount'];
            $installmentAmount = isset($data['installment_amount']) && (float) $data['installment_amount'] > 0
                ? (float) $data['installment_amount']
                : $paymentCycle->calculateInstallment($totalAmount);

            if ($targetUnitId) {
                $unit = Unit::findOrFail($targetUnitId);
                $unit->update(['status' => UnitStatus::Occupied]);
                $unitNumber = $unit->unit_number;
            } else {
                $unitNumber = $targetUnitNumber;
                $unit = Unit::firstOrCreate([
                    'property_id' => $property->id,
                    'unit_number' => $unitNumber,
                ], [
                    'status' => UnitStatus::Occupied,
                    'rent_price' => $installmentAmount,
                ]);
                $unit->update(['status' => UnitStatus::Occupied]);
            }

            $isNewTenant = ! empty($data['is_new_tenant']);

            if ($isNewTenant) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'national_id' => $data['national_id'] ?? null,
                    'username' => 'tenant_'.Str::random(8),
                    'password' => Hash::make('password123'),
                    'type' => UserType::Tenant,
                    'is_active' => true,
                ]);

                $tenant = TenantInfo::create([
                    'user_id' => $user->id,
                    'tenant_number' => null,
                    'property_id' => $property->id,
                    'owner_id' => $property->owner_id,
                    'collector_id' => $data['collector_id'],
                    'supervisor_id' => $creator->id,
                    'property_unit' => $property->name.' - وحدة '.$unitNumber,
                    'due_date' => $data['start_date'],
                    'monthly_target' => $installmentAmount,
                    'national_id' => $data['national_id'] ?? null,
                    'birth_date' => $data['birth_date'] ?? null,
                    'status' => 'regular',
                ]);

                $tenantNumber = 'T-'.(1000 + $tenant->id);
                $tenant->update(['tenant_number' => $tenantNumber]);
                $tenantId = $tenant->id;
            } else {
                $tenantId = (int) $data['tenant_id'];
                $tenant = TenantInfo::findOrFail($tenantId);
                $tenant->update([
                    'property_id' => $property->id,
                    'owner_id' => $property->owner_id,
                    'property_unit' => $property->name.' - وحدة '.$unitNumber,
                ]);
            }

            $contract = Contract::create([
                'contract_number' => null,
                'property_id' => $property->id,
                'tenant_id' => $tenantId,
                'unit_id' => $unit->id,
                'unit_number' => $unitNumber,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $totalAmount,
                'payment_cycle' => $paymentCycle,
                'installment_amount' => $installmentAmount,
                'claim_generation_mode' => $data['claim_generation_mode'],
                'status' => ContractStatus::Active,
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            $contractNumber = sprintf('CNT-%s-%05d', now()->format('Ym'), $contract->id);
            $contract->update([
                'contract_number' => $contractNumber,
            ]);

            // Generate dues schedule
            app(DueScheduleService::class)->generateForContract($contract);

            $monthlyTarget = round($installmentAmount * $paymentCycle->periodsPerYear() / 12, 2);

            $tenant->update([
                'contract_number' => $contractNumber,
                'monthly_target' => $monthlyTarget,
                'due_date' => $data['start_date'],
            ]);

            return $contract->load(['property', 'tenant.user', 'unit', 'dues']);
        });
    }

    /**
     * Update an existing contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateContract(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {
            $paymentCycle = isset($data['payment_cycle'])
                ? PaymentCycle::from($data['payment_cycle'])
                : $contract->payment_cycle;

            $totalAmount = isset($data['total_amount'])
                ? (float) $data['total_amount']
                : (float) $contract->total_amount;

            $installmentAmount = isset($data['installment_amount']) && (float) $data['installment_amount'] > 0
                ? (float) $data['installment_amount']
                : (isset($data['total_amount']) || isset($data['payment_cycle'])
                    ? $paymentCycle->calculateInstallment($totalAmount)
                    : (float) $contract->installment_amount);

            $contractUpdates = array_filter([
                'start_date' => $data['start_date'] ?? $contract->start_date,
                'end_date' => $data['end_date'] ?? $contract->end_date,
                'total_amount' => $totalAmount,
                'payment_cycle' => $paymentCycle,
                'installment_amount' => $installmentAmount,
                'claim_generation_mode' => $data['claim_generation_mode'] ?? $contract->claim_generation_mode,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $contract->notes,
            ], fn ($val) => $val !== null);

            if (! empty($data['unit_id'])) {
                $newUnit = Unit::findOrFail($data['unit_id']);
                if ($contract->unit_id !== $newUnit->id) {
                    $contract->unit?->update(['status' => UnitStatus::Vacant]);
                    $newUnit->update(['status' => UnitStatus::Occupied]);
                    $contractUpdates['unit_id'] = $newUnit->id;
                    $contractUpdates['unit_number'] = $newUnit->unit_number;
                }
            } elseif (isset($data['unit_number'])) {
                $contractUpdates['unit_number'] = $data['unit_number'];
                if ($data['unit_number'] !== $contract->unit_number) {
                    $contract->unit?->update(['status' => UnitStatus::Vacant]);
                    $newUnit = Unit::firstOrCreate([
                        'property_id' => $contract->property_id,
                        'unit_number' => $data['unit_number'],
                    ]);
                    $newUnit->update(['status' => UnitStatus::Occupied]);
                    $contractUpdates['unit_id'] = $newUnit->id;
                }
            }

            $contract->update($contractUpdates);

            // Regenerate dues schedule
            app(DueScheduleService::class)->generateForContract($contract);

            $monthlyTarget = round($installmentAmount * $paymentCycle->periodsPerYear() / 12, 2);

            $tenantUpdates = ['monthly_target' => $monthlyTarget];
            if (isset($data['unit_number'])) {
                $tenantUpdates['property_unit'] = $contract->property?->name.' - وحدة '.$data['unit_number'];
            }
            $contract->tenant?->update($tenantUpdates);

            return $contract->fresh(['property', 'tenant.user', 'unit', 'dues']);
        });
    }

    /**
     * Terminate / Close a contract ("إنهاء العقد").
     */
    public function terminateContract(Contract $contract, ?string $reason = null): Contract
    {
        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => 'لا يمكن إنهاء أو تجديد عقد غير ساري.']);
        }

        return DB::transaction(function () use ($contract, $reason) {
            $contract->update([
                'status' => ContractStatus::Closed,
                'closed_at' => now(),
                'closure_reason' => $reason,
            ]);

            $contract->unit?->update(['status' => UnitStatus::Vacant]);

            // المستأجر ميبقاش مطلوب منه حاجة بعد إنهاء العقد
            $contract->tenant?->update(['monthly_target' => 0]);

            return $contract->fresh(['property', 'tenant.user', 'unit']);
        });
    }

    /**
     * Renew an existing contract ("تجديد العقد").
     *
     * @param  array<string, mixed>  $data
     */
    public function renewContract(Contract $contract, array $data, User $creator): Contract
    {
        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => 'لا يمكن إنهاء أو تجديد عقد غير ساري.']);
        }

        $targetUnitNumber = $data['unit_number'] ?? $contract->unit_number;
        $existingActive = Contract::where('property_id', $contract->property_id)
            ->where('unit_number', $targetUnitNumber)
            ->where('status', ContractStatus::Active->value)
            ->where('id', '!=', $contract->id)
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($existingActive) {
            throw ValidationException::withMessages(['unit_number' => 'الوحدة عليها عقد سارٍ في نفس الفترة.']);
        }

        return DB::transaction(function () use ($contract, $data, $creator) {
            // 1. Mark existing active contract as renewed
            $contract->update([
                'status' => ContractStatus::Renewed,
                'closed_at' => now(),
            ]);

            $paymentCycle = PaymentCycle::from($data['payment_cycle']);
            $totalAmount = (float) $data['total_amount'];
            $installmentAmount = isset($data['installment_amount']) && (float) $data['installment_amount'] > 0
                ? (float) $data['installment_amount']
                : $paymentCycle->calculateInstallment($totalAmount);

            $contractNumber = 'CNT-'.now()->format('Ym').'-'.str_pad((string) (Contract::count() + 1), 4, '0', STR_PAD_LEFT);

            $targetUnitNumber = $data['unit_number'] ?? $contract->unit_number;
            $unit = Unit::firstOrCreate([
                'property_id' => $contract->property_id,
                'unit_number' => $targetUnitNumber,
            ]);
            $unit->update(['status' => UnitStatus::Occupied]);

            // 2. Create new active contract linked to the previous one
            $newContract = Contract::create([
                'contract_number' => null,
                'property_id' => $contract->property_id,
                'tenant_id' => $contract->tenant_id,
                'unit_id' => $unit->id,
                'unit_number' => $targetUnitNumber,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $totalAmount,
                'payment_cycle' => $paymentCycle,
                'installment_amount' => $installmentAmount,
                'claim_generation_mode' => $data['claim_generation_mode'],
                'status' => ContractStatus::Active,
                'previous_contract_id' => $contract->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            $contractNumber = sprintf('CNT-%s-%05d', now()->format('Ym'), $newContract->id);
            $newContract->update([
                'contract_number' => $contractNumber,
            ]);

            // Generate dues schedule
            app(DueScheduleService::class)->generateForContract($newContract);

            $monthlyTarget = round($installmentAmount * $paymentCycle->periodsPerYear() / 12, 2);

            $contract->tenant?->update([
                'contract_number' => $contractNumber,
                'monthly_target' => $monthlyTarget,
                'due_date' => $data['start_date'],
            ]);

            return $newContract->load(['property', 'tenant.user', 'unit', 'dues']);
        });
    }
}
