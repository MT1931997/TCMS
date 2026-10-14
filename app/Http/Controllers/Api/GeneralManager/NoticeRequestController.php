<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\NoticeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\IssueNoticeRequest;
use App\Http\Requests\GeneralManager\RejectNoticeRequest;
use App\Http\Resources\NoticeRequestResource;
use App\Models\NoticeRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeRequestController extends Controller
{
    /**
     * Display a listing of all notice requests for the supervisor.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NoticeRequest::with(['tenant.user', 'collector', 'supervisor']);

        // Search: tenant name, phone, tenant_number, contract_number, reason
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

        // Filter by tab matching supervisor UI tabs
        if ($tab = $request->input('tab')) {
            if ($tab === 'new' || $tab === 'pending_issuance') {
                $query->where('status', NoticeRequestStatus::PendingIssuance->value);
            } elseif ($tab === 'pending_approval' || $tab === 'pending_delivery_approval') {
                $query->where('status', NoticeRequestStatus::PendingApproval->value);
            }
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by collector
        if ($collectorId = $request->input('collector_id')) {
            $query->where('collector_id', $collectorId);
        }

        // Statistics for the top tabs
        $newRequestsCount = NoticeRequest::where('status', NoticeRequestStatus::PendingIssuance->value)->count();
        $pendingApprovalCount = NoticeRequest::where('status', NoticeRequestStatus::PendingApproval->value)->count();
        $allCount = NoticeRequest::count();

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
                'new_requests_count' => $newRequestsCount,
                'pending_approval_count' => $pendingApprovalCount,
                'all_count' => $allCount,
            ],
        ], 'تم جلب طلبات الإشعارات بنجاح.');
    }

    /**
     * Display the specified notice request.
     */
    public function show(NoticeRequest $noticeRequest): JsonResponse
    {
        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تفاصيل طلب الإشعار.'
        );
    }

    /**
     * Issue notice content and optional document for the request ("إصدار نص الإشعار").
     */
    public function issue(IssueNoticeRequest $request, NoticeRequest $noticeRequest): JsonResponse
    {
        $filePath = null;
        if ($request->hasFile('notice_file')) {
            $filePath = $request->file('notice_file')->store('notice_documents', 'public');
        }

        $noticeRequest->update([
            'notice_content' => $request->validated('notice_content'),
            'notice_file_path' => $filePath ?? $noticeRequest->notice_file_path,
            'issued_at' => now(),
            'supervisor_id' => $request->user()->id,
            'status' => NoticeRequestStatus::Issued,
        ]);

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تم إصدار نص الإشعار وإرساله للمحصل بنجاح.'
        );
    }

    /**
     * Approve the delivery proof submitted by the collector ("اعتماد إثبات التسليم").
     */
    public function approveDelivery(Request $request, NoticeRequest $noticeRequest): JsonResponse
    {
        if ($noticeRequest->status !== NoticeRequestStatus::PendingApproval && empty($noticeRequest->delivery_proof_path)) {
            return ApiResponse::error('لا يمكن اعتماد التسليم دون وجود إثبات تسليم مرفوع مسبقاً.', 422);
        }

        $noticeRequest->update([
            'supervisor_id' => $request->user()->id,
            'status' => NoticeRequestStatus::Approved,
        ]);

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تم اعتماد تسليم الإشعار بنجاح.'
        );
    }

    /**
     * Reject the notice request with a reason ("رفض طلب الإشعار").
     */
    public function reject(RejectNoticeRequest $request, NoticeRequest $noticeRequest): JsonResponse
    {
        $noticeRequest->update([
            'supervisor_id' => $request->user()->id,
            'rejection_reason' => $request->validated('rejection_reason'),
            'status' => NoticeRequestStatus::Rejected,
        ]);

        $noticeRequest->load(['tenant.user', 'collector', 'supervisor']);

        return ApiResponse::success(
            new NoticeRequestResource($noticeRequest),
            'تم رفض طلب الإشعار بنجاح.'
        );
    }
}
