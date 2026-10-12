<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\RejectPaymentRequest;
use App\Http\Requests\Supervisor\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApprovalController extends Controller
{
    /**
     * Display a listing of payments for supervisor review and approval.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['tenant.user', 'tenant.owner', 'collector', 'reviewer']);

        // Search: tenant name, phone, tenant_number, property_unit, receipt_number, collector name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('tenant_number', 'like', "%{$search}%")
                            ->orWhere('property_unit', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('collector', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Tab selection: 'pending' (بانتظار الاعتماد) or 'all' (جميع السدادات)
        $tab = $request->input('tab', 'pending');
        if ($tab === 'pending') {
            $query->where('status', PaymentStatus::Pending->value);
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Global counters for tabs
        $pendingCount = Payment::where('status', PaymentStatus::Pending->value)->count();
        $allCount = Payment::count();
        $approvedCount = Payment::whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])->count();
        $rejectedCount = Payment::whereIn('status', [PaymentStatus::Rejected->value, PaymentStatus::Cancelled->value])->count();

        $payments = $query->latest('payment_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success([
            'payments' => PaymentResource::collection($payments),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
            'tabs' => [
                'active_tab' => $tab,
                'pending_count' => $pendingCount,
                'all_count' => $allCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
            ],
        ], 'تم جلب قائمة السدادات بنجاح.');
    }

    /**
     * Display the specified payment details.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'reviewer']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تفاصيل دفعة السداد.'
        );
    }

    /**
     * Approve a payment (اعتماد السداد).
     */
    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'status' => PaymentStatus::Approved->value,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'reviewer']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم اعتماد السداد بنجاح.'
        );
    }

    /**
     * Reject a payment with reason (رفض السداد مع ذكر السبب).
     */
    public function reject(RejectPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'status' => PaymentStatus::Rejected->value,
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'reviewer']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم رفض السداد وتسجيل سبب الرفض بنجاح.'
        );
    }

    /**
     * Update payment details (تعديل السداد).
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            if ($data['status'] === PaymentStatus::Approved->value || $data['status'] === PaymentStatus::Completed->value) {
                $data['reviewed_by'] = $request->user()->id;
                $data['reviewed_at'] = now();
                $data['rejection_reason'] = null;
            } elseif ($data['status'] === PaymentStatus::Rejected->value || $data['status'] === PaymentStatus::Cancelled->value) {
                $data['reviewed_by'] = $request->user()->id;
                $data['reviewed_at'] = now();
            }
        }

        $payment->update($data);
        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'reviewer']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم تعديل بيانات السداد بنجاح.'
        );
    }

    /**
     * Delete a payment (حذف السداد).
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return ApiResponse::success(null, 'تم حذف السداد بنجاح.');
    }
}
