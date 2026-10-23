<?php

namespace App\Http\Resources;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $collected = $this->collectedAmount();
        $overdue = $this->overdueAmount();
        $tenantUser = $this->tenant?->user;

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'unit_number' => $this->unit_number,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $tenantUser?->name ?? '',
            'tenant_phone' => $tenantUser?->phone ?? '',
            'national_id' => $this->tenant?->national_id ?? $tenantUser?->national_id ?? '',
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $tenantUser?->name ?? '',
                'phone' => $tenantUser?->phone ?? '',
                'national_id' => $this->tenant->national_id ?? $tenantUser?->national_id ?? '',
            ] : null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'start_date_formatted' => $this->start_date?->translatedFormat('d F Y'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'end_date_formatted' => $this->end_date?->translatedFormat('d F Y'),
            'payment_cycle' => $this->payment_cycle?->value,
            'payment_cycle_label' => $this->payment_cycle?->label() ?? 'شهري',
            'installment_amount' => (float) $this->installment_amount,
            'installment_amount_formatted' => number_format((float) $this->installment_amount, 0).' ر.س',
            'installment_cycle_formatted' => number_format((float) $this->installment_amount, 0).' ر.س '.($this->payment_cycle?->label() ?? 'شهري'),
            'total_amount' => (float) $this->total_amount,
            'total_amount_formatted' => number_format((float) $this->total_amount, 0).' ر.س',
            'collected_amount' => $collected,
            'collected_amount_formatted' => number_format($collected, 0).' ر.س',
            'overdue_amount' => $overdue,
            'overdue_amount_formatted' => number_format($overdue, 0).' ر.س',
            'claim_generation_mode' => $this->claim_generation_mode?->value,
            'claim_generation_mode_label' => $this->claim_generation_mode?->label(),
            'status' => $this->status?->value ?? 'active',
            'status_label' => $this->status?->label() ?? 'ساري',
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
