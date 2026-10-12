<?php

namespace App\Http\Resources;

use App\Models\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantInfo
 */
class TenantDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $period = $request->query('period', now()->format('Y-m'));
        $target = (float) $this->monthly_target;
        $paid = $this->paidForPeriod($period);
        $remaining = max(0, $target - $paid);
        $percentage = $target > 0 ? min(100, round(($paid / $target) * 100)) : 0;

        return [
            'tenant_info' => [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'name' => $this->user?->name ?? '',
                'tenant_number' => $this->tenant_number,
                'contract_number' => $this->contract_number,
                'phone' => $this->user?->phone ?? '',
                'property_unit' => $this->property_unit,
                'due_date' => $this->due_date?->format('Y-m-d'),
                'due_date_formatted' => $this->due_date?->translatedFormat('d F Y'),
                'monthly_target' => $target,
                'collector' => $this->collector ? [
                    'id' => $this->collector->id,
                    'name' => $this->collector->name,
                ] : null,
                'owner' => $this->owner ? [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                ] : null,
                'national_id' => $this->national_id,
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'notes' => $this->notes,
                'status' => $this->status,
            ],
            'current_month_target' => [
                'period' => $period,
                'target_amount' => $target,
                'collected_amount' => $paid,
                'remaining_amount' => $remaining,
                'percentage' => $percentage,
                'due_date' => $this->due_date?->format('Y-m-d'),
                'due_date_formatted' => $this->due_date?->translatedFormat('d F Y'),
            ],
            'payments_ledger' => PaymentResource::collection($this->payments),
            'promises' => TenantPromiseResource::collection($this->promises),
            'notes_list' => TenantNoteResource::collection($this->notesList),
        ];
    }
}
