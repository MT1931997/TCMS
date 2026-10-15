<?php

namespace App\Http\Resources;

use App\Enums\MaintenanceWorkStatus;
use App\Models\MaintenanceWork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceWork
 */
class MaintenanceWorkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = $this->status instanceof MaintenanceWorkStatus
            ? $this->status
            : MaintenanceWorkStatus::tryFrom((string) $this->status);

        $attachments = $this->getFormattedAttachments();
        $filesCount = count($attachments);

        $ownerDecisionAt = $this->owner_decision_at;
        $ratedAt = $this->rated_at;
        $createdAt = $this->created_at;

        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'title' => $this->title,
            'description' => $this->description,
            'property_unit' => $this->property_unit,
            'status' => $statusEnum?->value ?? 'pending',
            'status_label' => $statusEnum?->label() ?? 'بانتظار قراركم',
            'files_count' => $filesCount,
            'files_count_formatted' => $filesCount === 0 ? 'لا توجد ملفات' : "{$filesCount} ملف",
            'attachments' => $attachments,
            'owner_decision_at' => $ownerDecisionAt?->format('Y-m-d H:i:s'),
            'owner_decision_at_formatted' => $ownerDecisionAt?->translatedFormat('d F Y'),
            'owner_notes' => $this->owner_notes,
            'owner_rejection_reason' => $this->owner_rejection_reason,
            'rating' => $this->rating,
            'rating_comment' => $this->rating_comment,
            'rated_at' => $ratedAt?->format('Y-m-d H:i:s'),
            'rated_at_formatted' => $ratedAt?->translatedFormat('d F Y - h:i A'),
            'owner_evaluation' => $this->getOwnerEvaluationSummary(),
            'created_at' => $createdAt?->format('Y-m-d H:i:s'),
            'created_at_formatted' => $createdAt?->translatedFormat('d F Y'),
            'maintenance_request_id' => $this->maintenance_request_id,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'phone' => $this->owner->phone,
                'email' => $this->owner->email,
            ] : null,
            'creator' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'type' => $this->creator->type?->value,
                'type_label' => $this->creator->type?->label(),
            ] : null,
        ];
    }
}
