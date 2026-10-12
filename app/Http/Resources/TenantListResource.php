<?php

namespace App\Http\Resources;

use App\Enums\TenantStatus;
use App\Models\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantInfo
 */
class TenantListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $period = $request->query('period', now()->format('Y-m'));
        $paid = $this->paidForPeriod($period);
        $target = (float) $this->monthly_target;
        $remaining = max(0, $target - $paid);

        $statusEnum = $this->status instanceof TenantStatus
            ? $this->status
            : TenantStatus::tryFrom((string) $this->status);

        $statusLabel = $statusEnum?->label() ?? 'منتظم';

        if ($target > 0 && $paid >= $target) {
            $statusLabel = TenantStatus::Paid->label();
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tenant_number' => $this->tenant_number,
            'contract_number' => $this->contract_number,
            'name' => $this->user?->name ?? '',
            'phone' => $this->user?->phone ?? '',
            'property_unit' => $this->property_unit,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_date_formatted' => $this->due_date?->translatedFormat('d F Y'),
            'monthly_target' => $target,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'collector' => $this->collector ? [
                'id' => $this->collector->id,
                'name' => $this->collector->name,
            ] : null,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
