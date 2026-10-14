<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'type' => $this->type?->value ?? (string) $this->type,
            'type_label' => $this->type?->label() ?? '',
            'type_description' => $this->type?->description() ?? '',
            'is_active' => (bool) $this->is_active,
            'is_current_user' => $request->user()?->id === $this->id,
            // Collector metrics
            'tenants_count' => $this->isCollector()
                ? $this->collectedTenants()->count()
                : ($this->isOwner() ? $this->ownedTenants()->count() : null),
            'collected_this_month' => $this->isCollector()
                ? (float) $this->collectorPayments()
                    ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])
                    ->where('period', now()->format('Y-m'))
                    ->sum('amount')
                : null,
            'target_this_month' => $this->isCollector()
                ? (float) $this->collectedTenants()->sum('monthly_target')
                : null,
            // Owner metrics
            'properties_count' => $this->isOwner()
                ? $this->ownedTenants()->distinct('property_unit')->count('property_unit')
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
