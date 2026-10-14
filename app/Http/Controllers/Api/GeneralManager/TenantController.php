<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StoreTenantRequest;
use App\Http\Requests\GeneralManager\UpdateTenantRequest;
use App\Http\Resources\TenantDetailResource;
use App\Http\Resources\TenantListResource;
use App\Models\TenantInfo;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants with search and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TenantInfo::query()->with(['user', 'collector', 'owner']);

        // Search: name, phone, tenant_number, contract_number, property_unit
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tenant_number', 'like', "%{$search}%")
                    ->orWhere('contract_number', 'like', "%{$search}%")
                    ->orWhere('property_unit', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by collector
        if ($collectorId = $request->query('collector_id')) {
            $query->where('collector_id', $collectorId);
        }

        // Filter by status
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
        ], 'تم جلب قائمة المستأجرين بنجاح.');
    }

    /**
     * Store a newly created tenant.
     * Creates User in `users` table and links `tenant_info`.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenant = DB::transaction(function () use ($validated, $request) {
            // 1. Create user account for tenant
            $user = User::create([
                'name' => $validated['name'],
                'username' => 'tenant_'.$validated['tenant_number'],
                'phone' => $validated['phone'],
                'type' => UserType::Tenant,
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);

            // 2. Create tenant info
            return TenantInfo::create([
                'user_id' => $user->id,
                'tenant_number' => $validated['tenant_number'],
                'contract_number' => $validated['contract_number'] ?? null,
                'property_unit' => $validated['property_unit'],
                'due_date' => $validated['due_date'],
                'monthly_target' => $validated['monthly_target'],
                'collector_id' => $validated['collector_id'],
                'owner_id' => $validated['owner_id'] ?? null,
                'supervisor_id' => $request->user()->id,
                'national_id' => $validated['national_id'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'regular',
            ]);
        });

        $tenant->load(['user', 'collector', 'owner', 'promises', 'notesList', 'payments']);

        return ApiResponse::success(
            new TenantDetailResource($tenant),
            'تمت إضافة المستأجر وحسابه بنجاح.',
            201
        );
    }

    /**
     * Display the specified tenant with complete target, ledger, promises, and notes.
     */
    public function show(TenantInfo $tenant): JsonResponse
    {
        $tenant->load(['user', 'collector', 'owner', 'promises.user', 'notesList.user', 'payments.collector']);

        return ApiResponse::success(
            new TenantDetailResource($tenant),
            'بيانات المستأجر وسجل المتابعة.'
        );
    }

    /**
     * Update the specified tenant info and user data.
     */
    public function update(UpdateTenantRequest $request, TenantInfo $tenant): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $tenant) {
            // Update User details if present
            $userUpdates = [];
            if (isset($validated['name'])) {
                $userUpdates['name'] = $validated['name'];
            }
            if (isset($validated['phone'])) {
                $userUpdates['phone'] = $validated['phone'];
            }

            if (! empty($userUpdates)) {
                $tenant->user->update($userUpdates);
            }

            // Update TenantInfo details
            $tenantInfoUpdates = collect($validated)->except(['name', 'phone'])->toArray();
            if (! empty($tenantInfoUpdates)) {
                $tenant->update($tenantInfoUpdates);
            }
        });

        $tenant->load(['user', 'collector', 'owner', 'promises.user', 'notesList.user', 'payments.collector']);

        return ApiResponse::success(
            new TenantDetailResource($tenant),
            'تم تحديث بيانات المستأجر بنجاح.'
        );
    }

    /**
     * Remove the specified tenant and user account.
     */
    public function destroy(TenantInfo $tenant): JsonResponse
    {
        $user = $tenant->user;
        $tenant->delete();
        $user?->delete();

        return ApiResponse::success(null, 'تم حذف المستأجر وحسابه بنجاح.');
    }
}
