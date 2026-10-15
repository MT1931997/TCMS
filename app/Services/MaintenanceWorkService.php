<?php

namespace App\Services;

use App\Enums\MaintenanceWorkStatus;
use App\Models\MaintenanceWork;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaintenanceWorkService
{
    /**
     * Get paginated maintenance work reports for supervisors with filters and tab counts.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     works: LengthAwarePaginator,
     *     tabs: array{
     *         active_tab: string,
     *         all_count: int,
     *         pending_count: int,
     *         approved_count: int,
     *         rejected_count: int,
     *         rated_count: int
     *     }
     * }
     */
    public function getListing(array $filters = [], int $perPage = 15): array
    {
        $query = MaintenanceWork::query()->with(['owner', 'creator', 'maintenanceRequest']);

        // Search in title, property_unit, work_order_number, description, or owner name
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by owner
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Tab selection: 'all', 'pending', 'approved', 'rejected'
        $tab = $filters['tab'] ?? 'all';
        if ($tab === 'pending') {
            $query->where('status', MaintenanceWorkStatus::Pending->value);
        } elseif ($tab === 'approved') {
            $query->where('status', MaintenanceWorkStatus::Approved->value);
        } elseif ($tab === 'rejected') {
            $query->where('status', MaintenanceWorkStatus::Rejected->value);
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Tab counters
        $allCount = MaintenanceWork::count();
        $pendingCount = MaintenanceWork::where('status', MaintenanceWorkStatus::Pending->value)->count();
        $approvedCount = MaintenanceWork::where('status', MaintenanceWorkStatus::Approved->value)->count();
        $rejectedCount = MaintenanceWork::where('status', MaintenanceWorkStatus::Rejected->value)->count();
        $ratedCount = MaintenanceWork::whereNotNull('rating')->count();

        $works = $query->latest('id')->paginate($perPage);

        return [
            'works' => $works,
            'tabs' => [
                'active_tab' => $tab,
                'all_count' => $allCount,
                'pending_count' => $pendingCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'rated_count' => $ratedCount,
            ],
        ];
    }

    /**
     * Create and send a new maintenance work report to an owner.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $uploadedFiles
     */
    public function create(array $data, User $creator, array $uploadedFiles = []): MaintenanceWork
    {
        $attachments = [];
        foreach ($uploadedFiles as $file) {
            if ($file instanceof UploadedFile) {
                $storedPath = $file->store('maintenance_works', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $storedPath,
                    'url' => Storage::disk('public')->url($storedPath),
                    'size_bytes' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ];
            }
        }

        $work = MaintenanceWork::create([
            'work_order_number' => MaintenanceWork::generateWorkOrderNumber(),
            'owner_id' => $data['owner_id'],
            'created_by' => $creator->id,
            'maintenance_request_id' => $data['maintenance_request_id'] ?? null,
            'property_unit' => $data['property_unit'],
            'title' => $data['title'],
            'description' => $data['description'],
            'attachments' => $attachments,
            'status' => MaintenanceWorkStatus::Pending->value,
        ]);

        return $work->load(['owner', 'creator', 'maintenanceRequest']);
    }

    /**
     * Update maintenance work report details.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $newFiles
     */
    public function update(MaintenanceWork $work, array $data, array $newFiles = []): MaintenanceWork
    {
        $attachments = $work->attachments ?? [];

        // Remove deleted attachments if specified
        if (! empty($data['deleted_attachments']) && is_array($data['deleted_attachments'])) {
            $deletedPaths = $data['deleted_attachments'];
            $attachments = array_values(array_filter($attachments, function ($item) use ($deletedPaths) {
                $path = is_array($item) ? ($item['path'] ?? null) : $item;
                if ($path && in_array($path, $deletedPaths)) {
                    Storage::disk('public')->delete($path);

                    return false;
                }

                return true;
            }));
            unset($data['deleted_attachments']);
        }

        // Add newly uploaded files
        foreach ($newFiles as $file) {
            if ($file instanceof UploadedFile) {
                $storedPath = $file->store('maintenance_works', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $storedPath,
                    'url' => Storage::disk('public')->url($storedPath),
                    'size_bytes' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ];
            }
        }

        $data['attachments'] = $attachments;
        unset($data['attachments_files']);

        $work->update($data);

        return $work->load(['owner', 'creator', 'maintenanceRequest']);
    }

    /**
     * Delete a maintenance work report and purge its uploaded files.
     */
    public function delete(MaintenanceWork $work): void
    {
        if (is_array($work->attachments)) {
            foreach ($work->attachments as $item) {
                $path = is_array($item) ? ($item['path'] ?? null) : $item;
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $work->delete();
    }

    /**
     * Get maintenance works for an owner with pending decision cards and decisions history.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     pending_works?: LengthAwarePaginator,
     *     history_works?: LengthAwarePaginator,
     *     pending_list?: Collection<int, MaintenanceWork>,
     *     history_list?: Collection<int, MaintenanceWork>,
     *     counters: array{
     *         pending_count: int,
     *         history_count: int,
     *         approved_count: int,
     *         rejected_count: int,
     *         all_count: int
     *     }
     * }
     */
    public function getOwnerListing(User $owner, array $filters = [], int $perPage = 15): array
    {
        $baseQuery = MaintenanceWork::query()
            ->with(['creator', 'maintenanceRequest'])
            ->where('owner_id', $owner->id);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $baseQuery->where(function ($q) use ($search) {
                $q->where('work_order_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $pendingCount = (clone $baseQuery)->where('status', MaintenanceWorkStatus::Pending->value)->count();
        $approvedCount = (clone $baseQuery)->where('status', MaintenanceWorkStatus::Approved->value)->count();
        $rejectedCount = (clone $baseQuery)->where('status', MaintenanceWorkStatus::Rejected->value)->count();
        $historyCount = $approvedCount + $rejectedCount;
        $allCount = (clone $baseQuery)->count();

        $counters = [
            'pending_count' => $pendingCount,
            'history_count' => $historyCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'all_count' => $allCount,
        ];

        $tab = $filters['tab'] ?? null;

        if ($tab === 'pending') {
            $pendingWorks = (clone $baseQuery)
                ->where('status', MaintenanceWorkStatus::Pending->value)
                ->latest('id')
                ->paginate($perPage);

            return [
                'pending_works' => $pendingWorks,
                'counters' => $counters,
            ];
        }

        if ($tab === 'history') {
            $historyWorks = (clone $baseQuery)
                ->whereIn('status', [
                    MaintenanceWorkStatus::Approved->value,
                    MaintenanceWorkStatus::Rejected->value,
                ])
                ->latest('owner_decision_at')
                ->latest('id')
                ->paginate($perPage);

            return [
                'history_works' => $historyWorks,
                'counters' => $counters,
            ];
        }

        // Default: return list of pending works and recent history for the view cards & table
        $pendingList = (clone $baseQuery)
            ->where('status', MaintenanceWorkStatus::Pending->value)
            ->latest('id')
            ->get();

        $historyList = (clone $baseQuery)
            ->whereIn('status', [
                MaintenanceWorkStatus::Approved->value,
                MaintenanceWorkStatus::Rejected->value,
            ])
            ->latest('owner_decision_at')
            ->latest('id')
            ->get();

        return [
            'pending_list' => $pendingList,
            'history_list' => $historyList,
            'counters' => $counters,
        ];
    }

    /**
     * Approve maintenance work by the owner ("قبول الصيانة").
     */
    public function approveByOwner(MaintenanceWork $work, ?string $notes = null): MaintenanceWork
    {
        $work->update([
            'status' => MaintenanceWorkStatus::Approved->value,
            'owner_decision_at' => now(),
            'owner_notes' => $notes,
            'owner_rejection_reason' => null,
        ]);

        return $work->load(['owner', 'creator', 'maintenanceRequest']);
    }

    /**
     * Reject maintenance work by the owner ("رفض الصيانة").
     */
    public function rejectByOwner(MaintenanceWork $work, ?string $reason = null, ?string $notes = null): MaintenanceWork
    {
        $finalReason = $reason ?? $notes ?? 'تم رفض عمل الصيانة من قبل المالك';

        $work->update([
            'status' => MaintenanceWorkStatus::Rejected->value,
            'owner_decision_at' => now(),
            'owner_rejection_reason' => $finalReason,
            'owner_notes' => $notes ?? $finalReason,
        ]);

        return $work->load(['owner', 'creator', 'maintenanceRequest']);
    }

    /**
     * Rate maintenance work quality by the owner ("قيّم جودة الصيانة").
     */
    public function rateByOwner(MaintenanceWork $work, int $rating, ?string $comment = null): MaintenanceWork
    {
        $work->update([
            'rating' => $rating,
            'rating_comment' => $comment,
            'rated_at' => now(),
        ]);

        return $work->load(['owner', 'creator', 'maintenanceRequest']);
    }

    /**
     * Get approved maintenance summary for export PDF.
     *
     * @return array<string, mixed>
     */
    public function getApprovedExportSummary(?int $ownerId = null): array
    {
        $query = MaintenanceWork::query()
            ->with(['owner', 'creator'])
            ->where('status', MaintenanceWorkStatus::Approved->value);

        if ($ownerId) {
            $query->where('owner_id', $ownerId);
        }

        $records = $query->latest('owner_decision_at')->get();

        return [
            'exported_at' => now()->format('Y-m-d H:i:s'),
            'exported_at_formatted' => now()->translatedFormat('d F Y - h:i A'),
            'total_approved_works' => $records->count(),
            'records' => $records->map(fn (MaintenanceWork $w) => [
                'work_order_number' => $w->work_order_number,
                'title' => $w->title,
                'property_unit' => $w->property_unit,
                'owner_name' => $w->owner?->name,
                'created_by' => $w->creator?->name,
                'decision_date' => $w->owner_decision_at?->translatedFormat('d F Y'),
                'rating' => $w->rating,
                'rating_comment' => $w->rating_comment,
                'notes' => $w->owner_notes,
            ]),
        ];
    }
}
