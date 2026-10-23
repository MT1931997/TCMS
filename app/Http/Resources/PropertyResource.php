<?php

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $period = $request->query('period', now()->format('Y-m'));

        $activeContractsCount = $this->activeContractsCount();
        $collectedThisMonth = $this->collectedAmountForPeriod($period);
        $overdueAmount = $this->overdueAmount();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->owner?->name ?? '',
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'username' => $this->owner->username,
                'phone' => $this->owner->phone,
                'email' => $this->owner->email,
            ] : null,
            'units_count' => $this->tenants()->count(),
            'active_contracts_count' => $activeContractsCount,
            'collected_this_month' => $collectedThisMonth,
            'collected_this_month_formatted' => number_format($collectedThisMonth, 0).' ر.س',
            'overdue_amount' => $overdueAmount,
            'overdue_amount_formatted' => number_format($overdueAmount, 0).' ر.س',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
