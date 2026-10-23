<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\RenewContractRequest;
use App\Http\Requests\GeneralManager\StoreContractRequest;
use App\Http\Requests\GeneralManager\StorePropertyRequest;
use App\Http\Requests\GeneralManager\TerminateContractRequest;
use App\Http\Requests\GeneralManager\UpdateContractRequest;
use App\Http\Requests\GeneralManager\UpdatePropertyRequest;
use App\Http\Resources\ContractResource;
use App\Http\Resources\PreviousContractResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\TenantWithoutContractResource;
use App\Models\Contract;
use App\Models\Property;
use App\Services\PropertyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService
    ) {}

    /**
     * Display a listing of properties ("إدارة الأملاك والعقود").
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->has('per_page') ? (int) $request->query('per_page') : null;

        $filters = [
            'search' => $request->query('search'),
            'owner_id' => $request->query('owner_id'),
            'period' => $request->query('period', now()->format('Y-m')),
        ];

        $result = $this->propertyService->getListing($filters, $perPage);

        $responseData = [
            'properties' => PropertyResource::collection($result['properties']),
            'statistics' => $result['statistics'],
            'owners' => $result['owners'],
        ];

        if ($result['properties'] instanceof LengthAwarePaginator) {
            $responseData['pagination'] = [
                'total' => $result['properties']->total(),
                'per_page' => $result['properties']->perPage(),
                'current_page' => $result['properties']->currentPage(),
                'last_page' => $result['properties']->lastPage(),
            ];
        }

        return ApiResponse::success($responseData, 'تم جلب قائمة العقارات بنجاح.');
    }

    /**
     * Store a newly created property ("إضافة عقار جديد").
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->propertyService->create(
            $request->validated(),
            $request->user()
        );

        $property->load(['owner', 'tenants']);

        return ApiResponse::success(
            new PropertyResource($property),
            'تمت إضافة العقار بنجاح.',
            201
        );
    }

    /**
     * Display the specified property page ("فتح العقار"):
     * Shows property info, statistics, active contracts table,
     * tenants without active contract table, and previous contracts table.
     */
    public function show(Request $request, Property $property): JsonResponse
    {
        $details = $this->propertyService->getPropertyDetails(
            $property,
            $request->query('period')
        );

        return ApiResponse::success([
            'property' => new PropertyResource($details['property']),
            'statistics' => $details['statistics'],
            'active_contracts' => ContractResource::collection($details['active_contracts']),
            'tenants_without_active_contract' => TenantWithoutContractResource::collection($details['tenants_without_active_contract']),
            'previous_contracts' => PreviousContractResource::collection($details['previous_contracts']),
        ], 'بيانات العقار وعقوده ووحداته.');
    }

    /**
     * Update the specified property ("تعديل العقار").
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $updatedProperty = $this->propertyService->update(
            $property,
            $request->validated()
        );

        return ApiResponse::success(
            new PropertyResource($updatedProperty),
            'تم تحديث بيانات العقار بنجاح.'
        );
    }

    /**
     * Remove the specified property ("حذف العقار").
     */
    public function destroy(Property $property): JsonResponse
    {
        $this->propertyService->delete($property);

        return ApiResponse::success(null, 'تم حذف العقار بنجاح.');
    }

    /**
     * Get contract dropdown options (payment cycles, claim generation modes, collectors, tenants).
     */
    public function contractOptions(Property $property): JsonResponse
    {
        $options = $this->propertyService->getContractOptions($property);

        return ApiResponse::success($options, 'خيارات نموذج إضافة العقد.');
    }

    /**
     * Store a new contract for this property ("إضافة عقد").
     */
    public function storeContract(StoreContractRequest $request, Property $property): JsonResponse
    {
        $contract = $this->propertyService->createContract(
            $property,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            'تمت إضافة العقد بنجاح.',
            201
        );
    }

    /**
     * Update an existing contract for this property ("تعديل العقد").
     */
    public function updateContract(UpdateContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        $updatedContract = $this->propertyService->updateContract(
            $contract,
            $request->validated()
        );

        return ApiResponse::success(
            new ContractResource($updatedContract),
            'تم تحديث بيانات العقد بنجاح.'
        );
    }

    /**
     * Terminate an active contract ("إنهاء العقد").
     */
    public function terminateContract(TerminateContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        $terminatedContract = $this->propertyService->terminateContract(
            $contract,
            $request->input('closure_reason')
        );

        return ApiResponse::success(
            new PreviousContractResource($terminatedContract),
            'تم إنهاء العقد بنجاح ونقله لسجل العقود السابقة.'
        );
    }

    /**
     * Renew an existing contract ("تجديد العقد").
     */
    public function renewContract(RenewContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        $newContract = $this->propertyService->renewContract(
            $contract,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($newContract),
            'تم تجديد العقد بنجاح ونقل العقد السابق لسجل العقود السابقة.',
            201
        );
    }
}
