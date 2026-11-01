<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentNoteResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerPaymentController extends Controller
{
    /**
     * Display a listing of owner payments (سدادات الملاك).
     * Filtered by tenants associated with owners.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['tenant.user', 'tenant.owner', 'collector', 'reviewer', 'notesList.user'])
            ->whereHas('tenant', function ($tq) {
                $tq->whereNotNull('owner_id');
            });

        // Search: owner name, tenant name, unit/property, receipt number
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('property_unit', 'like', "%{$search}%")
                            ->orWhere('tenant_number', 'like', "%{$search}%")
                            ->orWhereHas('owner', function ($oq) use ($search) {
                                $oq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            })
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Filter by specific owner
        if ($ownerId = $request->input('owner_id')) {
            $query->whereHas('tenant', function ($tq) use ($ownerId) {
                $tq->where('owner_id', $ownerId);
            });
        }

        // Filter by period (e.g. 2026-10)
        if ($period = $request->input('period')) {
            $query->where('period', $period);
        }

        // Filter by status (approved, pending, rejected)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Summary statistics
        $statsQuery = clone $query;
        $totalCount = $statsQuery->count();
        $totalAmount = (float) $statsQuery->sum('amount');
        $approvedAmount = (float) (clone $query)->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Completed->value])->sum('amount');
        $pendingAmount = (float) (clone $query)->where('status', PaymentStatus::Pending->value)->sum('amount');

        $payments = $query->latest('payment_date')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

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
        ], 'تم جلب سدادات الملاك بنجاح.');
    }

    /**
     * Display notes/comments for a specific owner payment.
     */
    public function notes(Payment $payment): JsonResponse
    {
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
     * Add a new note/comment on an owner payment by supervisor.
     */
    public function storeNote(Request $request, Payment $payment): JsonResponse
    {
        $content = $request->input('content') ?? $request->input('notes');

        $validated = validator(['content' => $content], [
            'content' => ['required', 'string', 'max:1000'],
        ], [
            'content.required' => 'نص الملاحظة مطلوب.',
            'content.string' => 'الملاحظة يجب أن تكون نصاً صالحاً.',
            'content.max' => 'الملاحظة يجب ألا تتجاوز 1000 حرف.',
        ])->validate();

        $note = $payment->notesList()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $payment->update([
            'notes' => $validated['content'],
        ]);

        $note->load('user');

        return ApiResponse::success(
            new PaymentNoteResource($note),
            'تمت إضافة الملاحظة بنجاح.',
            201
        );
    }

    /**
     * Update comments/notes for a specific payment in owner payments.
     */
    public function updateNotes(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'notes.max' => 'التعليق يجب ألا يتجاوز 1000 حرف.',
        ]);

        $payment->update($validated);

        if (! empty($validated['notes'])) {
            $payment->notesList()->create([
                'user_id' => $request->user()->id,
                'content' => $validated['notes'],
            ]);
        }

        $payment->load(['tenant.user', 'tenant.owner', 'collector', 'notesList.user']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم تحديث التعليقات بنجاح.'
        );
    }
}
