<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\ApproveMaintenanceRequestRequest;
use App\Http\Requests\GeneralManager\RejectMaintenanceRequestRequest;
use App\Http\Requests\GeneralManager\UpdateMaintenanceRequestRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    public function __construct(
        protected MaintenanceRequestService $service
    ) {}

    /**
     * Display a listing of tenant maintenance requests for supervisor review.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->getListing(
            filters: [
                'search' => $request->input('search'),
                'tab' => $request->input('tab', 'pending'),
                'status' => $request->input('status'),
                'owner_id' => $request->input('owner_id'),
            ],
            perPage: $request->integer('per_page', 15)
        );

        return ApiResponse::success([
            'requests' => MaintenanceRequestResource::collection($result['requests']),
            'pagination' => [
                'total' => $result['requests']->total(),
                'per_page' => $result['requests']->perPage(),
                'current_page' => $result['requests']->currentPage(),
                'last_page' => $result['requests']->lastPage(),
            ],
            'tabs' => $result['tabs'],
        ], 'تم جلب قائمة طلبات الصيانة بنجاح.');
    }

    /**
     * Display the specified maintenance request details.
     */
    public function show(MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $maintenanceRequest->load(['tenant.user', 'owner', 'reviewer']);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest),
            'تفاصيل طلب الصيانة.'
        );
    }

    /**
     * Approve a maintenance request (اعتماد طلب الصيانة).
     */
    public function approve(ApproveMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->service->approve(
            maintenanceRequest: $maintenanceRequest,
            ownerId: (int) $validated['owner_id'],
            propertyUnit: $request->filled('property_unit') ? $validated['property_unit'] : null,
            notes: $validated['notes'] ?? null,
            reviewer: $request->user()
        );

        return ApiResponse::success(
            new MaintenanceRequestResource($updated),
            'تم اعتماد طلب الصيانة بنجاح وتحويله إلى المالك للموافقة.'
        );
    }

    /**
     * Reject a maintenance request with reason (رفض طلب الصيانة).
     */
    public function reject(RejectMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->service->reject(
            maintenanceRequest: $maintenanceRequest,
            rejectionReason: $validated['rejection_reason'],
            reviewer: $request->user()
        );

        return ApiResponse::success(
            new MaintenanceRequestResource($updated),
            'تم رفض طلب الصيانة وتسجيل سبب الرفض بنجاح.'
        );
    }

    /**
     * Update maintenance request details (تعديل الطلب).
     */
    public function update(UpdateMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $updated = $this->service->update(
            maintenanceRequest: $maintenanceRequest,
            data: $request->validated(),
            reviewer: $request->user()
        );

        return ApiResponse::success(
            new MaintenanceRequestResource($updated),
            'تم تعديل بيانات طلب الصيانة بنجاح.'
        );
    }

    /**
     * Delete a maintenance request (حذف الطلب).
     */
    public function destroy(MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->service->delete($maintenanceRequest);

        return ApiResponse::success(null, 'تم حذف طلب الصيانة بنجاح.');
    }
}
