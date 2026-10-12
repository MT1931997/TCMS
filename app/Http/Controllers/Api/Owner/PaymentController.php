<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StorePaymentNoteRequest;
use App\Http\Resources\PaymentNoteResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments for the authenticated owner's properties ("سدادات وحداتي").
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();

        $query = Payment::with(['tenant.user', 'tenant.owner', 'collector', 'notesList.user'])
            ->whereHas('tenant', function ($tq) use ($owner) {
                $tq->where('owner_id', $owner->id);
            });

        // Search: tenant name, property unit, receipt number
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('property_unit', 'like', "%{$search}%")
                            ->orWhere('tenant_number', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Filter by period (e.g. 2026-10)
        if ($period = $request->input('period')) {
            $query->where('period', $period);
        }

        // Filter by status (approved, pending, etc.)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Calculate summary statistics
        $statsQuery = clone $query;
        $totalCount = $statsQuery->count();
        $totalAmount = (float) $statsQuery->sum('amount');
        $approvedAmount = (float) (clone $query)->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])->sum('amount');
        $pendingAmount = (float) (clone $query)->where('status', PaymentStatus::Pending->value)->sum('amount');

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
            'statistics' => [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'approved_amount' => $approvedAmount,
                'pending_amount' => $pendingAmount,
            ],
        ], 'تم جلب قائمة سدادات وحداتك بنجاح.');
    }

    /**
     * Display a specific payment for the owner.
     */
    public function show(Payment $payment, Request $request): JsonResponse
    {
        if ($payment->tenant?->owner_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لعرض هذا السداد.', 403);
        }

        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'notesList.user']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تفاصيل السداد.'
        );
    }

    /**
     * Display notes/comments for a specific payment.
     */
    public function notes(Payment $payment, Request $request): JsonResponse
    {
        if ($payment->tenant?->owner_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لعرض ملاحظات هذا السداد.', 403);
        }

        $notes = $payment->notesList()->with('user')->get();

        return ApiResponse::success([
            'payment' => [
                'id' => $payment->id,
                'property_unit' => $payment->tenant?->property_unit,
                'amount' => (float) $payment->amount,
                'period' => $payment->period,
            ],
            'notes' => PaymentNoteResource::collection($notes),
        ], 'تم استرجاع ملاحظات السداد بنجاح.');
    }

    /**
     * Add a new note/comment on a payment by the owner.
     */
    public function storeNote(StorePaymentNoteRequest $request, Payment $payment): JsonResponse
    {
        if ($payment->tenant?->owner_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لإضافة ملاحظة على هذا السداد.', 403);
        }

        $note = $payment->notesList()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $note->load('user');

        return ApiResponse::success(
            new PaymentNoteResource($note),
            'تمت إضافة الملاحظة بنجاح.',
            201
        );
    }
}
