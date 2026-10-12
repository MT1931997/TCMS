<?php

namespace App\Http\Resources;

use App\Models\NoticeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin NoticeRequest
 */
class NoticeRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => [
                'id' => $this->tenant_id,
                'name' => $this->tenant?->user?->name ?? '',
                'tenant_number' => $this->tenant?->tenant_number ?? '',
                'contract_number' => $this->tenant?->contract_number,
                'property_unit' => $this->tenant?->property_unit ?? '',
                'phone' => $this->tenant?->user?->phone ?? '',
            ],
            'collector' => [
                'id' => $this->collector_id,
                'name' => $this->collector?->name ?? '',
            ],
            'supervisor' => $this->supervisor ? [
                'id' => $this->supervisor->id,
                'name' => $this->supervisor->name,
            ] : null,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'details' => $this->getDetailsSummary(),
            'notice_content' => $this->notice_content,
            'notice_file_url' => $this->notice_file_path ? Storage::disk('public')->url($this->notice_file_path) : null,
            'issued_at' => $this->issued_at?->format('Y-m-d H:i'),
            'issued_at_formatted' => $this->issued_at?->translatedFormat('d F Y - h:i A'),
            'delivery_proof_url' => $this->delivery_proof_path ? Storage::disk('public')->url($this->delivery_proof_path) : null,
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'delivery_date_formatted' => $this->delivery_date?->translatedFormat('d F Y'),
            'delivery_notes' => $this->delivery_notes,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'created_at_formatted' => $this->created_at?->translatedFormat('d F Y'),
        ];
    }
}
