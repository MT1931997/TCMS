<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\MaintenanceRequestOwnerStatus;
use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ApproveMaintenanceRequestRequest;
use App\Http\Requests\Owner\RejectMaintenanceRequestRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    /**
     * Display a listing of maintenance requests for the authenticated owner ("أعمال الصيانة").
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();

        $baseQuery = MaintenanceRequest::query()
            ->with(['tenant.user', 'owner', 'reviewer'])
            ->where('owner_id', $owner->id)
            ->where('supervisor_status', MaintenanceRequestSupervisorStatus::Approved->value);

        // Search: title, property_unit, tenant_name, ticket_number
        if ($search = $request->input('search')) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%");
            });
        }

        // Global counters for owner
        $pendingCount = (clone $baseQuery)
            ->where('owner_status', MaintenanceRequestOwnerStatus::Pending->value)
            ->count();

        $approvedCount = (clone $baseQuery)
            ->where('owner_status', MaintenanceRequestOwnerStatus::Approved->value)
            ->count();

        $rejectedCount = (clone $baseQuery)
            ->where('owner_status', MaintenanceRequestOwnerStatus::Rejected->value)
            ->count();

        $historyCount = $approvedCount + $rejectedCount;
        $allCount = (clone $baseQuery)->count();

        // Optional tab filter
        $tab = $request->input('tab');

        if ($tab === 'pending') {
            $pendingRequests = (clone $baseQuery)
                ->where('owner_status', MaintenanceRequestOwnerStatus::Pending->value)
                ->latest('reviewed_at')
                ->latest('id')
                ->paginate($request->integer('per_page', 15));

            return ApiResponse::success([
                'requests' => MaintenanceRequestResource::collection($pendingRequests),
                'pagination' => [
                    'total' => $pendingRequests->total(),
                    'per_page' => $pendingRequests->perPage(),
                    'current_page' => $pendingRequests->currentPage(),
                    'last_page' => $pendingRequests->lastPage(),
                ],
                'counters' => [
                    'pending_count' => $pendingCount,
                    'history_count' => $historyCount,
                    'approved_count' => $approvedCount,
                    'rejected_count' => $rejectedCount,
                    'all_count' => $allCount,
                ],
            ], 'تم جلب طلبات الصيانة بانتظار موافقتكم بنجاح.');
        }

        if ($tab === 'history') {
            $historyRequests = (clone $baseQuery)
                ->whereIn('owner_status', [
                    MaintenanceRequestOwnerStatus::Approved->value,
                    MaintenanceRequestOwnerStatus::Rejected->value,
                ])
                ->latest('owner_decision_at')
                ->latest('id')
                ->paginate($request->integer('per_page', 15));

            return ApiResponse::success([
                'requests' => MaintenanceRequestResource::collection($historyRequests),
                'pagination' => [
                    'total' => $historyRequests->total(),
                    'per_page' => $historyRequests->perPage(),
                    'current_page' => $historyRequests->currentPage(),
                    'last_page' => $historyRequests->lastPage(),
                ],
                'counters' => [
                    'pending_count' => $pendingCount,
                    'history_count' => $historyCount,
                    'approved_count' => $approvedCount,
                    'rejected_count' => $rejectedCount,
                    'all_count' => $allCount,
                ],
            ], 'تم جلب سجل قرارات الصيانة بنجاح.');
        }

        // Default view: return both pending requests and history log
        $pendingList = (clone $baseQuery)
            ->where('owner_status', MaintenanceRequestOwnerStatus::Pending->value)
            ->latest('reviewed_at')
            ->latest('id')
            ->get();

        $historyList = (clone $baseQuery)
            ->whereIn('owner_status', [
                MaintenanceRequestOwnerStatus::Approved->value,
                MaintenanceRequestOwnerStatus::Rejected->value,
            ])
            ->latest('owner_decision_at')
            ->latest('id')
            ->get();

        return ApiResponse::success([
            'pending_requests' => MaintenanceRequestResource::collection($pendingList),
            'history_requests' => MaintenanceRequestResource::collection($historyList),
            'counters' => [
                'pending_count' => $pendingCount,
                'history_count' => $historyCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'all_count' => $allCount,
            ],
        ], 'تم جلب أعمال وطلبات الصيانة بنجاح.');
    }

    /**
     * Display the specified maintenance request details for the owner.
     */
    public function show(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        if ($maintenanceRequest->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بالاطلاع على هذا الطلب.', 403);
        }

        $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest),
            'تفاصيل طلب الصيانة.'
        );
    }

    /**
     * Approve maintenance request by the owner ("قبول الصيانة").
     */
    public function approve(ApproveMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        if ($maintenanceRequest->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك باتخاذ قرار بشأن هذا الطلب.', 403);
        }

        $maintenanceRequest->update([
            'owner_status' => MaintenanceRequestOwnerStatus::Approved->value,
            'owner_decision_at' => now(),
            'owner_notes' => $request->validated('notes'),
            'owner_rejection_reason' => null,
        ]);

        $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest),
            'تم قبول طلب الصيانة بنجاح.'
        );
    }

    /**
     * Reject maintenance request by the owner ("رفض الصيانة").
     */
    public function reject(RejectMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        if ($maintenanceRequest->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك باتخاذ قرار بشأن هذا الطلب.', 403);
        }

        $rejectionReason = $request->validated('rejection_reason') ?? $request->validated('notes');

        $maintenanceRequest->update([
            'owner_status' => MaintenanceRequestOwnerStatus::Rejected->value,
            'owner_decision_at' => now(),
            'owner_rejection_reason' => $rejectionReason,
            'owner_notes' => $request->validated('notes') ?? $rejectionReason,
        ]);

        $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest),
            'تم تسجيل رفض طلب الصيانة بنجاح.'
        );
    }
}
