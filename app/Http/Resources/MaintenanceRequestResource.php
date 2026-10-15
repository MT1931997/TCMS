<?php

namespace App\Http\Resources;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin MaintenanceRequest
 */
class MaintenanceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supervisorStatusValue = $this->supervisor_status instanceof MaintenanceRequestSupervisorStatus
            ? $this->supervisor_status->value
            : ($this->supervisor_status ? (string) $this->supervisor_status : 'pending_review');

        $supervisorStatusEnum = $supervisorStatusValue ? MaintenanceRequestSupervisorStatus::tryFrom($supervisorStatusValue) : null;

        $ownerStatusValue = $this->owner_status instanceof MaintenanceRequestOwnerStatus
            ? $this->owner_status->value
            : ($this->owner_status ? (string) $this->owner_status : null);

        $ownerStatusEnum = $ownerStatusValue ? MaintenanceRequestOwnerStatus::tryFrom($ownerStatusValue) : null;

        // Normalize attachments to always include absolute URLs
        $attachments = array_map(function ($attachment) {
            if (is_string($attachment)) {
                return [
                    'path' => $attachment,
                    'url' => asset('storage/'.$attachment),
                    'file_name' => basename($attachment),
                    'file_type' => $this->detectFileType($attachment),
                ];
            }

            if (is_array($attachment)) {
                $path = $attachment['path'] ?? null;

                return [
                    'path' => $path,
                    'url' => $path ? asset('storage/'.$path) : ($attachment['url'] ?? null),
                    'file_name' => $attachment['file_name'] ?? ($path ? basename($path) : 'file'),
                    'file_type' => $attachment['file_type'] ?? ($path ? $this->detectFileType($path) : 'file'),
                    'file_size' => $attachment['file_size'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                ];
            }

            return $attachment;
        }, (array) ($this->attachments ?? []));

        $birthDate = $this->birth_date ? Carbon::parse($this->birth_date) : null;
        $createdAt = $this->created_at ? Carbon::parse($this->created_at) : null;
        $reviewedAt = $this->reviewed_at ? Carbon::parse($this->reviewed_at) : null;
        $ownerDecisionAt = $this->owner_decision_at ? Carbon::parse($this->owner_decision_at) : null;

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->description,
            'tenant_name' => $this->tenant_name,
            'national_id' => $this->national_id,
            'birth_date' => $birthDate?->format('Y-m-d'),
            'birth_date_formatted' => $birthDate?->translatedFormat('d F Y'),
            'phone' => $this->phone,
            'property_unit' => $this->property_unit,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'address' => $this->address,
            'google_maps_url' => $this->getGoogleMapsUrl(),
            'attachments' => $attachments,
            'supervisor_status' => $supervisorStatusEnum?->value ?? 'pending_review',
            'supervisor_status_label' => $supervisorStatusEnum?->label() ?? 'بانتظار المراجعة',
            'supervisor_approval_text' => $this->getSupervisorApprovalText(),
            'owner_status' => $ownerStatusEnum?->value,
            'owner_status_label' => $ownerStatusEnum?->label(),
            'status' => $supervisorStatusEnum?->value ?? 'pending_review',
            'status_label' => $supervisorStatusEnum?->label() ?? 'بانتظار المراجعة',
            'owner_decision_at' => $ownerDecisionAt?->format('Y-m-d H:i:s'),
            'owner_decision_at_formatted' => $ownerDecisionAt?->translatedFormat('d F Y'),
            'owner_notes' => $this->owner_notes,
            'owner_rejection_reason' => $this->owner_rejection_reason,
            'is_auto_linked' => $this->isAutoLinked(),
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'tenant_number' => $this->tenant->tenant_number,
                'contract_number' => $this->tenant->contract_number,
                'property_unit' => $this->tenant->property_unit,
                'name' => $this->tenant->user?->name,
                'phone' => $this->tenant->user?->phone,
            ] : null,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'phone' => $this->owner->phone,
                'email' => $this->owner->email,
            ] : null,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'reviewed_at' => $reviewedAt?->format('Y-m-d H:i:s'),
            'reviewed_at_formatted' => $reviewedAt?->translatedFormat('d F Y - h:i A'),
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $createdAt?->format('Y-m-d H:i:s'),
            'created_at_formatted' => $createdAt?->translatedFormat('d F Y'),
            'created_at_human' => $createdAt?->diffForHumans(),
        ];
    }

    /**
     * Detect general file category from extension.
     */
    protected function detectFileType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
            return 'image';
        }

        if (in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
            return 'video';
        }

        return 'document';
    }
}
