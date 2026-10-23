<?php

namespace App\Http\Controllers\Api\RealEstateSupervisor;

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
     * Ensure the user has real estate supervisor or general manager privileges.
     */
    protected function authorizeSupervisor(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isRealEstateSupervisor() && ! $user->isGeneralManager())) {
            return ApiResponse::error('غير مصرح لك بالوصول إلى موديول مشرف العقارات.', 403);
        }

        return null;
    }

    /**
     * Display a listing of properties for real estate supervisor.
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Store a newly created property.
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Display the specified property page ("فتح العقار").
     */
    public function show(Request $request, Property $property): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Update the specified property.
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Remove the specified property.
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $this->propertyService->delete($property);

        return ApiResponse::success(null, 'تم حذف العقار بنجاح.');
    }

    /**
     * Get contract dropdown options.
     */
    public function contractOptions(Request $request, Property $property): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $options = $this->propertyService->getContractOptions($property);

        return ApiResponse::success($options, 'خيارات نموذج إضافة العقد.');
    }

    /**
     * Store a new contract for this property.
     */
    public function storeContract(StoreContractRequest $request, Property $property): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Update an existing contract for this property.
     */
    public function updateContract(UpdateContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Terminate an active contract.
     */
    public function terminateContract(TerminateContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
     * Renew an existing contract.
     */
    public function renewContract(RenewContractRequest $request, Property $property, Contract $contract): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

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
