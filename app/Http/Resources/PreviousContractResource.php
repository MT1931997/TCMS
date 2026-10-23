<?php

namespace App\Http\Resources;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class PreviousContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $collected = $this->collectedAmount();
        $tenantUser = $this->tenant?->user;

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'unit_number' => $this->unit_number,
            'tenant_name' => $tenantUser?->name ?? '',
            'tenant_phone' => $tenantUser?->phone ?? '',
            'from_date' => $this->start_date?->format('Y-m-d'),
            'from_date_formatted' => $this->start_date?->translatedFormat('d F Y'),
            'to_date' => $this->end_date?->format('Y-m-d'),
            'to_date_formatted' => $this->end_date?->translatedFormat('d F Y'),
            'total_amount' => (float) $this->total_amount,
            'total_amount_formatted' => number_format((float) $this->total_amount, 0).' ر.س',
            'collected_amount' => $collected,
            'collected_amount_formatted' => number_format($collected, 0).' ر.س',
            'status' => $this->status?->value ?? 'closed',
            'status_label' => $this->status?->label() ?? 'مُغلق',
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closure_reason' => $this->closure_reason,
        ];
    }
}
