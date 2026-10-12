<?php

namespace App\Http\Controllers\Api\Collector;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantDetailResource;
use App\Http\Resources\TenantListResource;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants assigned to the authenticated collector ("مستأجروني").
     */
    public function index(Request $request): JsonResponse
    {
        $collector = $request->user();

        $query = TenantInfo::query()
            ->where('collector_id', $collector->id)
            ->with(['user', 'collector', 'owner']);

        // Search: name, phone, tenant_number, property_unit
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tenant_number', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 15);
        $tenants = $query->latest()->paginate($perPage);

        $period = $request->query('period', now()->format('Y-m'));

        return ApiResponse::success([
            'tenants' => TenantListResource::collection($tenants),
            'pagination' => [
                'total' => $tenants->total(),
                'per_page' => $tenants->perPage(),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
            ],
            'period' => $period,
        ], 'تم جلب قائمة مستأجري المحصل بنجاح.');
    }

    /**
     * Display the specified tenant details for the collector.
     */
    public function show(Request $request, TenantInfo $tenant): JsonResponse
    {
        // Ensure collector only accesses their assigned tenants
        if ($tenant->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية للوصول لبيانات هذا المستأجر.', 403);
        }

        $tenant->load(['user', 'collector', 'owner', 'promises.user', 'notesList.user', 'payments.collector']);

        return ApiResponse::success(
            new TenantDetailResource($tenant),
            'بيانات المستأجر وسجل المتابعة.'
        );
    }
}
