<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'tenant_number' => $this->tenant->tenant_number,
                'property_unit' => $this->tenant->property_unit,
                'name' => $this->tenant->user?->name,
                'phone' => $this->tenant->user?->phone,
            ] : null,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'payment_date_formatted' => $this->payment_date?->translatedFormat('d F Y'),
            'payment_method' => $this->payment_method,
            'receipt_number' => $this->receipt_number,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_path ? asset('storage/'.$this->attachment_path) : null,
            'amount' => (float) $this->amount,
            'period' => $this->period,
            'status' => ($status = $this->status instanceof PaymentStatus ? $this->status : PaymentStatus::tryFrom((string) $this->status))?->value ?? (string) $this->status,
            'status_label' => $status?->label() ?? (string) $this->status,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'owner' => $this->tenant?->owner ? [
                'id' => $this->tenant->owner->id,
                'name' => $this->tenant->owner->name,
            ] : null,
            'collector' => $this->collector ? [
                'id' => $this->collector->id,
                'name' => $this->collector->name,
            ] : null,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'reviewed_at_formatted' => $this->reviewed_at?->translatedFormat('d F Y - h:i A'),
            'notes_list' => PaymentNoteResource::collection($this->whenLoaded('notesList')),
        ];
    }
}
