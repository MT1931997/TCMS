<?php

namespace App\Services;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaintenanceRequestService
{
    /**
     * Get paginated maintenance requests with search, filtering, and tab counts.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     requests: LengthAwarePaginator,
     *     tabs: array{
     *         active_tab: string,
     *         pending_count: int,
     *         all_count: int,
     *         approved_count: int,
     *         rejected_count: int
     *     }
     * }
     */
    public function getListing(array $filters = [], int $perPage = 15): array
    {
        $query = MaintenanceRequest::query()->with(['tenant.user', 'owner', 'reviewer']);

        // Search: ticket_number, title, tenant_name, national_id, phone, property_unit, description
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Tab selection: 'pending' (بانتظار المراجعة) or 'all' (جميع الطلبات) or specific status
        $tab = $filters['tab'] ?? 'pending';
        if ($tab === 'pending') {
            $query->where('supervisor_status', MaintenanceRequestSupervisorStatus::PendingReview->value);
        } elseif (! empty($filters['supervisor_status'])) {
            $query->where('supervisor_status', $filters['supervisor_status']);
        } elseif (! empty($filters['status'])) {
            $query->where('supervisor_status', $filters['status']);
        }

        // Filter by owner
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Tab counters
        $pendingCount = MaintenanceRequest::where('supervisor_status', MaintenanceRequestSupervisorStatus::PendingReview->value)->count();
        $allCount = MaintenanceRequest::count();
        $approvedCount = MaintenanceRequest::where('supervisor_status', MaintenanceRequestSupervisorStatus::Approved->value)->count();
        $rejectedCount = MaintenanceRequest::where('supervisor_status', MaintenanceRequestSupervisorStatus::Rejected->value)->count();

        $requests = $query->latest('id')->paginate($perPage);

        return [
            'requests' => $requests,
            'tabs' => [
                'active_tab' => $tab,
                'pending_count' => $pendingCount,
                'all_count' => $allCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
            ],
        ];
    }

    /**
     * Approve a maintenance request and forward it to the owner.
     */
    public function approve(
        MaintenanceRequest $maintenanceRequest,
        int $ownerId,
        ?string $propertyUnit,
        ?string $notes,
        User $reviewer
    ): MaintenanceRequest {
        $maintenanceRequest->update([
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Approved->value,
            'owner_status' => MaintenanceRequestOwnerStatus::Pending->value,
            'owner_id' => $ownerId,
            'property_unit' => ! empty($propertyUnit) ? $propertyUnit : $maintenanceRequest->property_unit,
            'notes' => $notes ?? $maintenanceRequest->notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'owner_decision_at' => null,
            'owner_notes' => null,
            'owner_rejection_reason' => null,
        ]);

        return $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);
    }

    /**
     * Reject a maintenance request with reason.
     */
    public function reject(
        MaintenanceRequest $maintenanceRequest,
        string $rejectionReason,
        User $reviewer
    ): MaintenanceRequest {
        $maintenanceRequest->update([
            'supervisor_status' => MaintenanceRequestSupervisorStatus::Rejected->value,
            'owner_status' => null,
            'rejection_reason' => $rejectionReason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);
    }

    /**
     * Update maintenance request details.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        MaintenanceRequest $maintenanceRequest,
        array $data,
        ?User $reviewer = null
    ): MaintenanceRequest {
        $status = $data['supervisor_status'] ?? $data['status'] ?? null;
        unset($data['status']);

        if ($status) {
            $data['supervisor_status'] = $status;
            if ($status === MaintenanceRequestSupervisorStatus::Approved->value) {
                $data['owner_status'] = MaintenanceRequestOwnerStatus::Pending->value;
                if ($reviewer) {
                    $data['reviewed_by'] = $reviewer->id;
                    $data['reviewed_at'] = now();
                }
                $data['rejection_reason'] = null;
            } elseif ($status === MaintenanceRequestSupervisorStatus::Rejected->value) {
                $data['owner_status'] = null;
                if ($reviewer) {
                    $data['reviewed_by'] = $reviewer->id;
                    $data['reviewed_at'] = now();
                }
            }
        }

        $maintenanceRequest->update($data);

        return $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);
    }

    /**
     * Delete a maintenance request.
     */
    public function delete(MaintenanceRequest $maintenanceRequest): bool
    {
        return (bool) $maintenanceRequest->delete();
    }
}
