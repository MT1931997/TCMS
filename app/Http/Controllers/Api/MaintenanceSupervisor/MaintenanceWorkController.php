<?php

namespace App\Http\Controllers\Api\MaintenanceSupervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceWork\StoreMaintenanceWorkRequest;
use App\Http\Requests\MaintenanceWork\UpdateMaintenanceWorkRequest;
use App\Http\Resources\MaintenanceWorkResource;
use App\Models\MaintenanceWork;
use App\Services\MaintenanceWorkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceWorkController extends Controller
{
    public function __construct(
        protected MaintenanceWorkService $service
    ) {}

    /**
     * Ensure the authenticated user has maintenance supervisor or general manager privileges.
     */
    protected function authorizeSupervisor(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isMaintenanceSupervisor() && ! $user->isGeneralManager())) {
            return ApiResponse::error('غير مصرح لك بالوصول إلى موديول مشرف الصيانة.', 403);
        }

        return null;
    }

    /**
     * Display a listing of sent maintenance work reports ("التقارير المرسلة").
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $result = $this->service->getListing(
            filters: [
                'search' => $request->input('search'),
                'tab' => $request->input('tab', 'all'),
                'status' => $request->input('status'),
                'owner_id' => $request->input('owner_id'),
            ],
            perPage: $request->integer('per_page', 15)
        );

        return ApiResponse::success([
            'works' => MaintenanceWorkResource::collection($result['works']),
            'pagination' => [
                'total' => $result['works']->total(),
                'per_page' => $result['works']->perPage(),
                'current_page' => $result['works']->currentPage(),
                'last_page' => $result['works']->lastPage(),
            ],
            'tabs' => $result['tabs'],
        ], 'تم جلب تقارير أعمال الصيانة بنجاح.');
    }

    /**
     * Create and send a new maintenance work report to an owner ("إرسال تقرير صيانة جديد").
     */
    public function store(StoreMaintenanceWorkRequest $request): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $files = $request->file('attachments') ?? [];
        if (! is_array($files) && $files !== null) {
            $files = [$files];
        }

        $work = $this->service->create(
            data: $request->validated(),
            creator: $request->user(),
            uploadedFiles: $files
        );

        return ApiResponse::success(
            new MaintenanceWorkResource($work),
            'تم إرسال تقرير الصيانة للمالك بنجاح.',
            201
        );
    }

    /**
     * Display the specified maintenance work report.
     */
    public function show(Request $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $maintenanceWork->load(['owner', 'creator', 'maintenanceRequest']);

        return ApiResponse::success(
            new MaintenanceWorkResource($maintenanceWork),
            'تفاصيل تقرير الصيانة.'
        );
    }

    /**
     * Update the specified maintenance work report.
     */
    public function update(UpdateMaintenanceWorkRequest $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $files = $request->file('attachments') ?? [];
        if (! is_array($files) && $files !== null) {
            $files = [$files];
        }

        $updated = $this->service->update(
            work: $maintenanceWork,
            data: $request->validated(),
            newFiles: $files
        );

        return ApiResponse::success(
            new MaintenanceWorkResource($updated),
            'تم تحديث تقرير الصيانة بنجاح.'
        );
    }

    /**
     * Delete the specified maintenance work report.
     */
    public function destroy(Request $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $this->service->delete($maintenanceWork);

        return ApiResponse::success(null, 'تم حذف تقرير الصيانة بنجاح.');
    }

    /**
     * Export certified/approved maintenance summary ("تصدير الصيانة المعتمدة (PDF)").
     */
    public function exportPdf(Request $request): JsonResponse
    {
        if ($forbidden = $this->authorizeSupervisor($request)) {
            return $forbidden;
        }

        $summary = $this->service->getApprovedExportSummary(
            ownerId: $request->integer('owner_id') ?: null
        );

        return ApiResponse::success($summary, 'بيانات تقرير الصيانة المعتمدة للتصدير.');
    }
}
