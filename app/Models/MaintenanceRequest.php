<?php

namespace App\Models;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_number',
    'tenant_name',
    'national_id',
    'birth_date',
    'phone',
    'property_unit',
    'title',
    'description',
    'latitude',
    'longitude',
    'address',
    'attachments',
    'supervisor_status',
    'owner_status',
    'tenant_id',
    'owner_id',
    'reviewed_by',
    'reviewed_at',
    'notes',
    'rejection_reason',
    'owner_decision_at',
    'owner_notes',
    'owner_rejection_reason',
])]
class MaintenanceRequest extends Model
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supervisor_status' => MaintenanceRequestSupervisorStatus::class,
            'owner_status' => MaintenanceRequestOwnerStatus::class,
            'birth_date' => 'date',
            'reviewed_at' => 'datetime',
            'owner_decision_at' => 'datetime',
            'attachments' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * The tenant profile linked to this maintenance request (if matched in system).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantInfo::class, 'tenant_id');
    }

    /**
     * The owner assigned or linked to this maintenance request.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The supervisor who reviewed (approved or rejected) this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Generate Google Maps URL from latitude and longitude if available.
     */
    public function getGoogleMapsUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
        }

        return null;
    }

    /**
     * Determine if this request is automatically linked to a registered tenant.
     */
    public function isAutoLinked(): bool
    {
        return $this->tenant_id !== null;
    }

    /**
     * Get descriptive text for supervisor approval date.
     */
    public function getSupervisorApprovalText(): ?string
    {
        if ($this->reviewed_at) {
            return 'اعتمده المشرف بتاريخ '.$this->reviewed_at->translatedFormat('d F Y');
        }

        return null;
    }

    /**
     * Scope query to requests waiting for a specific owner's approval.
     */
    public function scopePendingOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId)
            ->where('supervisor_status', MaintenanceRequestSupervisorStatus::Approved->value)
            ->where('owner_status', MaintenanceRequestOwnerStatus::Pending->value);
    }

    /**
     * Scope query to owner's history of decisions (approved or rejected).
     */
    public function scopeOwnerHistory(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId)
            ->whereIn('owner_status', [
                MaintenanceRequestOwnerStatus::Approved->value,
                MaintenanceRequestOwnerStatus::Rejected->value,
            ]);
    }

    /**
     * Generate a unique readable ticket number for maintenance requests.
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'MNT-'.now()->format('Ym');
        $latest = static::query()
            ->where('ticket_number', 'like', "{$prefix}-%")
            ->latest('id')
            ->value('ticket_number');

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        $ticket = sprintf('%s-%04d', $prefix, $sequence);
        while (static::where('ticket_number', $ticket)->exists()) {
            $sequence++;
            $ticket = sprintf('%s-%04d', $prefix, $sequence);
        }

        return $ticket;
    }
}
