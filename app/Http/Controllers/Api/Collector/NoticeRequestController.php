<?php

namespace App\Http\Controllers\Api\Collector;

use App\Enums\NoticeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collector\StoreNoticeRequest;
use App\Http\Requests\Collector\SubmitDeliveryProofRequest;
use App\Http\Resources\NoticeRequestResource;
use App\Models\NoticeRequest;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeRequestController extends Controller
{
    /**
     * Display a listing of notice requests initiated by the authenticated collector ("طلباتي").
     */
    public function index(Request $request): JsonResponse
    {
        $collector = $request->user();

        $query = NoticeRequest::with(['tenant.user', 'collector', 'supervisor'])
            ->where('collector_id', $collector->id);

        // Search: tenant name, phone, tenant_number, property_unit, reason
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('tenant_number', 'like', "%{$search}%")
                            ->orWhere('contract_number', 'like', "%{$search}%")
                            ->orWhere('property_unit', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Summary statistics
        $statsQuery = clone $query;
        $totalCount = $statsQuery->count();
        $pendingIssuanceCount = (clone $query)->where('status', NoticeRequestStatus::PendingIssuance->value)->count();
        $issuedCount = (clone $query)->where('status', NoticeRequestStatus::Issued->value)->count();
        $pendingApprovalCount = (clone $query)->where('status', NoticeRequestStatus::PendingApproval->value)->count();
        $approvedCount = (clone $query)->where('status', NoticeRequestStatus::Approved->value)->count();

        $requests = $query->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success([
            'requests' => NoticeRequestResource::collection($requests),
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
            'statistics' => [
                'total_count' => $totalCount,
                'pending_issuance_count' => $pendingIssuanceCount,
                'issued_count' => $issuedCount,
                'pending_approval_count' => $pendingApprovalCount,
                'approved_count' => $approvedCount,
            ],
        ], 'تم جلب قائمة طلبات الإشعارات بنجاح.');
    }

    /**
     * Store a new notice request for a tenant ("طلب إشعار جديد").
     */
    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $tenant = TenantInfo::findOrFail($request->validated('tenant_id'));

        // Check collector permission on tenant
        if ($tenant->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لطلب إشعار لهذا المستأجر.', 403);
        }

        $noticeRequest = NoticeRequest::create([
            'tenant_id' => $tenant->id,
            'collector_id' => $request->user()->id,
            'supervisor_id' => $tenant->supervisor_id,
            'reason' => $request->validated('reason'),
            'status' => NoticeRequestStatus::PendingIssuance,
        ]);

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تم إرسال طلب الإشعار للمشرف بنجاح.',
            201
        );
    }

    /**
     * Display the specified notice request details.
     */
    public function show(NoticeRequest $noticeRequest, Request $request): JsonResponse
    {
        if ($noticeRequest->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لعرض هذا الطلب.', 403);
        }

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تفاصيل طلب الإشعار.'
        );
    }

    /**
     * Submit delivery proof image for an issued notice ("إرفاق صورة إثبات التسليم").
     */
    public function submitDeliveryProof(SubmitDeliveryProofRequest $request, NoticeRequest $noticeRequest): JsonResponse
    {
        if ($noticeRequest->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لتعديل هذا الطلب.', 403);
        }

        if (! in_array($noticeRequest->status, [NoticeRequestStatus::Issued, NoticeRequestStatus::PendingApproval], true)) {
            return ApiResponse::error('لا يمكن إرفاق إثبات التسليم إلا بعد قيام المشرف بإصدار الإشعار.', 422);
        }

        $file = $request->file('delivery_proof') ?? $request->file('image');
        $path = $file->store('notice_delivery_proofs', 'public');
        $notes = $request->input('delivery_notes') ?? $request->input('notes');

        $noticeRequest->update([
            'delivery_proof_path' => $path,
            'delivery_date' => $request->input('delivery_date') ?? now()->toDateString(),
            'delivery_notes' => $notes,
            'status' => NoticeRequestStatus::PendingApproval,
        ]);

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تم إرسال إثبات التسليم للمراجعة بنجاح.'
        );
    }
}
