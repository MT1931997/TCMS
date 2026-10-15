<?php

namespace App\Models;

use App\Enums\MaintenanceWorkStatus;
use Database\Factories\MaintenanceWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'work_order_number',
    'owner_id',
    'created_by',
    'maintenance_request_id',
    'property_unit',
    'title',
    'description',
    'attachments',
    'status',
    'owner_decision_at',
    'owner_notes',
    'owner_rejection_reason',
    'rating',
    'rating_comment',
    'rated_at',
])]
class MaintenanceWork extends Model
{
    /** @use HasFactory<MaintenanceWorkFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MaintenanceWorkStatus::class,
            'attachments' => 'array',
            'owner_decision_at' => 'datetime',
            'rated_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    /**
     * The property owner to whom this maintenance work report was sent.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The supervisor or general manager who created/sent this maintenance work report.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Optional linked tenant maintenance request if this work was spawned from one.
     */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    /**
     * Scope query to reports waiting for the owner's decision ("بانتظار قراركم").
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MaintenanceWorkStatus::Pending->value);
    }

    /**
     * Scope query to reports approved by the owner ("مقبولة").
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', MaintenanceWorkStatus::Approved->value);
    }

    /**
     * Scope query to reports rejected by the owner ("مرفوضة").
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', MaintenanceWorkStatus::Rejected->value);
    }

    /**
     * Scope query to reports assigned to a specific owner.
     */
    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Get human-readable evaluation summary for the sent reports list.
     * In UI: "لم يقيّم بعد" or formatted rating like "5 نجوم (مقبولة)".
     */
    public function getOwnerEvaluationSummary(): string
    {
        if ($this->rating !== null) {
            $statusLabel = $this->status?->label() ?? '';

            return "{$this->rating} نجوم".($statusLabel ? " - {$statusLabel}" : '');
        }

        if ($this->status === MaintenanceWorkStatus::Pending) {
            return 'لم يقيّم بعد';
        }

        return $this->status?->label() ?? 'لم يقيّم بعد';
    }

    /**
     * Format attachment file entries with public accessible URLs and metadata.
     *
     * @return array<int, array{name: string, url: string, path: string, type: string, size_bytes: ?int}>
     */
    public function getFormattedAttachments(): array
    {
        $files = $this->attachments ?? [];
        if (! is_array($files)) {
            return [];
        }

        $formatted = [];
        foreach ($files as $file) {
            if (is_string($file)) {
                $formatted[] = [
                    'name' => basename($file),
                    'url' => Storage::disk('public')->url($file),
                    'path' => $file,
                    'type' => $this->detectMimeCategory($file),
                    'size_bytes' => Storage::disk('public')->exists($file) ? Storage::disk('public')->size($file) : null,
                ];
            } elseif (is_array($file) && isset($file['path'])) {
                $path = $file['path'];
                $formatted[] = [
                    'name' => $file['name'] ?? basename($path),
                    'url' => $file['url'] ?? Storage::disk('public')->url($path),
                    'path' => $path,
                    'type' => $file['type'] ?? $this->detectMimeCategory($path),
                    'size_bytes' => $file['size_bytes'] ?? (Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null),
                ];
            }
        }

        return $formatted;
    }

    /**
     * Detect file category (image, video, document).
     */
    protected function detectMimeCategory(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            return 'image';
        }

        if (in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
            return 'video';
        }

        return 'file';
    }

    /**
     * Generate a unique readable work order number (e.g., WRK-202610-0001).
     */
    public static function generateWorkOrderNumber(): string
    {
        $prefix = 'WRK-'.now()->format('Ym');
        $latest = static::query()
            ->where('work_order_number', 'like', "{$prefix}-%")
            ->latest('id')
            ->value('work_order_number');

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        $workNumber = sprintf('%s-%04d', $prefix, $sequence);
        while (static::where('work_order_number', $workNumber)->exists()) {
            $sequence++;
            $workNumber = sprintf('%s-%04d', $prefix, $sequence);
        }

        return $workNumber;
    }
}
