<?php

namespace App\Http\Resources;

use App\Models\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantInfo
 */
class TenantWithoutContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentPeriod = now()->format('Y-m');
        $paidThisMonth = $this->paidForPeriod($currentPeriod);
        $target = (float) $this->monthly_target;
        $overdue = max(0, $target - $paidThisMonth);

        return [
            'tenant_id' => $this->id,
            'user_id' => $this->user_id,
            'tenant_name' => $this->user?->name ?? '',
            'phone' => $this->user?->phone ?? '',
            'national_id' => $this->national_id ?? $this->user?->national_id ?? '',
            'property_unit' => $this->property_unit,
            'overdue_amount' => $overdue,
            'overdue_amount_formatted' => number_format($overdue, 0).' ر.س',
            'collected_this_month' => $paidThisMonth,
            'collected_this_month_formatted' => number_format($paidThisMonth, 0).' ر.س',
        ];
    }
}
