<?php

namespace App\Http\Controllers\Api\Collector;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collector\StoreCollectorPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a list of payments made by the authenticated collector (سداداتي).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Payment::with(['tenant.user', 'collector'])
            ->where('collector_id', $user->id);

        // Filter by search (name, phone, tenant_number, receipt_number)
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
                    });
            });
        }

        // Filter by status (pending, approved, rejected)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by period (e.g. 2026-10)
        if ($period = $request->input('period')) {
            $query->where('period', $period);
        }

        // Calculate summary statistics
        $statsQuery = clone $query;
        $totalCount = $statsQuery->count();
        $totalAmount = (float) $statsQuery->sum('amount');
        $pendingCount = (clone $query)->where('status', 'pending')->count();
        $approvedCount = (clone $query)->whereIn('status', ['approved', 'completed'])->count();

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
                'pending_count' => $pendingCount,
                'approved_count' => $approvedCount,
            ],
        ], 'تم استرجاع قائمة السدادات بنجاح.');
    }

    /**
     * Upload a new payment directly from the "رفع سداد جديد" page.
     */
    public function storeDirect(StoreCollectorPaymentRequest $request): JsonResponse
    {
        $tenant = TenantInfo::findOrFail($request->validated('tenant_id'));

        return $this->processPaymentStore($request, $tenant);
    }

    /**
     * Record a new payment (رفع سداد) for a specific tenant in the URL.
     */
    public function store(StoreCollectorPaymentRequest $request, TenantInfo $tenant): JsonResponse
    {
        return $this->processPaymentStore($request, $tenant);
    }

    /**
     * Display a specific payment.
     */
    public function show(Payment $payment, Request $request): JsonResponse
    {
        if ($payment->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لعرض هذا السداد.', 403);
        }

        $payment->load(['tenant.user', 'collector']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم استرجاع تفاصيل السداد بنجاح.'
        );
    }

    /**
     * Common payment storage handler.
     */
    protected function processPaymentStore(StoreCollectorPaymentRequest $request, TenantInfo $tenant): JsonResponse
    {
        if ($tenant->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لتسجيل سداد لهذا المستأجر.', 403);
        }

        $validated = $request->validated();
        $validated['tenant_id'] = $tenant->id;
        $validated['collector_id'] = $request->user()->id;
        $validated['status'] = PaymentStatus::Pending->value; // Default to pending (قيد المراجعة) until approved by supervisor

        if (empty($validated['payment_date'])) {
            $validated['payment_date'] = now()->toDateString();
        }

        if (empty($validated['period'])) {
            $validated['period'] = date('Y-m', strtotime($validated['payment_date']));
        }

        // Handle attachment file upload if present
        if ($request->hasFile('receipt_attachment')) {
            $path = $request->file('receipt_attachment')->store('receipts', 'public');
            $validated['attachment_path'] = $path;
        }

        $payment = Payment::create($validated);
        $payment->load(['tenant.user', 'collector']);

        return ApiResponse::success(
            new PaymentResource($payment),
            'تم تسجيل دفعة السداد وإرسالها للمراجعة بنجاح.',
            201
        );
    }
}
