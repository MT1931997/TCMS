<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Record a new payment in the tenant's ledger.
     */
    public function store(StorePaymentRequest $request, TenantInfo $tenant): JsonResponse
    {
        $validated = $request->validated();
        $validated['collector_id'] = $request->user()->id;
        $validated['status'] = PaymentStatus::Completed->value;

        if (empty($validated['period'])) {
            $validated['period'] = date('Y-m', strtotime($validated['payment_date']));
        }

        $payment = $tenant->payments()->create($validated);
        $payment->load('collector');

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم تسجيل دفعة السداد بنجاح.',
            201
        );
    }

    /**
     * Delete a payment record.
     */
    public function destroy(TenantInfo $tenant, Payment $payment): JsonResponse
    {
        if ($payment->tenant_id !== $tenant->id) {
            return ApiResponse::error('دفعة السداد غير مرتبطة بهذا المستأجر.', 404);
        }

        $payment->delete();

        return ApiResponse::success(null, 'تم حذف دفعة السداد بنجاح.');
    }
}
