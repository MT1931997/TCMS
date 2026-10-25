<?php

namespace App\Http\Resources;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceRequest
 */
class PublicMaintenanceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'status' => $this->supervisor_status?->value,
            'status_label' => $this->supervisor_status?->label(),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
