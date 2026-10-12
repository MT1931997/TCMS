<?php

namespace App\Http\Resources;

use App\Enums\PromiseStatus;
use App\Models\TenantPromise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantPromise
 */
class TenantPromiseResource extends JsonResource
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
            'promise_date' => $this->promise_date?->format('Y-m-d'),
            'promise_date_formatted' => $this->promise_date?->translatedFormat('d F Y'),
            'note' => $this->note,
            'status' => ($status = $this->status instanceof PromiseStatus ? $this->status : PromiseStatus::tryFrom((string) $this->status))?->value ?? (string) $this->status,
            'status_label' => $status?->label() ?? (string) $this->status,
            'created_by' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => $this->created_at?->translatedFormat('d F Y - h:i A'),
        ];
    }
}
