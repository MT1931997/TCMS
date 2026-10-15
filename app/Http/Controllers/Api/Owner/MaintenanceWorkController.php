<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceWork\OwnerDecisionRequest;
use App\Http\Requests\MaintenanceWork\RateMaintenanceWorkRequest;
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
     * Display a listing of maintenance work reports for the owner ("أعمال الصيانة").
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();

        $result = $this->service->getOwnerListing(
            owner: $owner,
            filters: [
                'search' => $request->input('search'),
                'tab' => $request->input('tab'),
            ],
            perPage: $request->integer('per_page', 15)
        );

        if (isset($result['pending_works'])) {
            return ApiResponse::success([
                'works' => MaintenanceWorkResource::collection($result['pending_works']),
                'pagination' => [
                    'total' => $result['pending_works']->total(),
                    'per_page' => $result['pending_works']->perPage(),
                    'current_page' => $result['pending_works']->currentPage(),
                    'last_page' => $result['pending_works']->lastPage(),
                ],
                'counters' => $result['counters'],
            ], 'تم جلب أعمال الصيانة بانتظار قراركم بنجاح.');
        }

        if (isset($result['history_works'])) {
            return ApiResponse::success([
                'works' => MaintenanceWorkResource::collection($result['history_works']),
                'pagination' => [
                    'total' => $result['history_works']->total(),
                    'per_page' => $result['history_works']->perPage(),
                    'current_page' => $result['history_works']->currentPage(),
                    'last_page' => $result['history_works']->lastPage(),
                ],
                'counters' => $result['counters'],
            ], 'تم جلب سجل قرارات أعمال الصيانة بنجاح.');
        }

        return ApiResponse::success([
            'pending_works' => MaintenanceWorkResource::collection($result['pending_list']),
            'history_works' => MaintenanceWorkResource::collection($result['history_list']),
            'counters' => $result['counters'],
        ], 'تم جلب أعمال الصيانة بنجاح.');
    }

    /**
     * Display the specified maintenance work report for the owner.
     */
    public function show(Request $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($maintenanceWork->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بالاطلاع على هذا التقرير.', 403);
        }

        $maintenanceWork->load(['creator', 'maintenanceRequest']);

        return ApiResponse::success(
            new MaintenanceWorkResource($maintenanceWork),
            'تفاصيل تقرير عمل الصيانة.'
        );
    }

    /**
     * Owner decision: Accept the maintenance work ("قبول الصيانة").
     */
    public function approve(OwnerDecisionRequest $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($maintenanceWork->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك باتخاذ قرار بشأن هذا التقرير.', 403);
        }

        $approved = $this->service->approveByOwner(
            work: $maintenanceWork,
            notes: $request->validated('notes')
        );

        return ApiResponse::success(
            new MaintenanceWorkResource($approved),
            'تم تسجيل قبول عمل الصيانة بنجاح.'
        );
    }

    /**
     * Owner decision: Reject the maintenance work ("رفض الصيانة").
     */
    public function reject(OwnerDecisionRequest $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($maintenanceWork->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك باتخاذ قرار بشأن هذا التقرير.', 403);
        }

        $rejected = $this->service->rejectByOwner(
            work: $maintenanceWork,
            reason: $request->validated('rejection_reason'),
            notes: $request->validated('notes')
        );

        return ApiResponse::success(
            new MaintenanceWorkResource($rejected),
            'تم تسجيل رفض عمل الصيانة بنجاح.'
        );
    }

    /**
     * Owner evaluation: Rate maintenance quality ("قيّم جودة الصيانة").
     */
    public function rate(RateMaintenanceWorkRequest $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($maintenanceWork->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بتقييم هذا التقرير.', 403);
        }

        $rated = $this->service->rateByOwner(
            work: $maintenanceWork,
            rating: $request->validated('rating'),
            comment: $request->validated('rating_comment')
        );

        return ApiResponse::success(
            new MaintenanceWorkResource($rated),
            'تم إرسال تقييم جودة الصيانة بنجاح.'
        );
    }

    /**
     * Export certified/approved maintenance works summary for the authenticated owner ("تصدير الصيانة المعتمدة (PDF)").
     */
    public function exportApprovedPdf(Request $request): JsonResponse
    {
        $summary = $this->service->getApprovedExportSummary(
            ownerId: $request->user()->id
        );

        return ApiResponse::success($summary, 'بيانات تقارير الصيانة المعتمدة للمالك للتصدير.');
    }

    /**
     * Download / View single maintenance work report PDF summary.
     */
    public function pdf(Request $request, MaintenanceWork $maintenanceWork): JsonResponse
    {
        if ($maintenanceWork->owner_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بتحميل هذا التقرير.', 403);
        }

        $maintenanceWork->load(['creator', 'maintenanceRequest', 'owner']);

        return ApiResponse::success([
            'report_title' => 'تقرير عمل صيانة معتمد',
            'work_order_number' => $maintenanceWork->work_order_number,
            'title' => $maintenanceWork->title,
            'property_unit' => $maintenanceWork->property_unit,
            'description' => $maintenanceWork->description,
            'status' => $maintenanceWork->status?->value,
            'status_label' => $maintenanceWork->status?->label(),
            'owner_decision_date' => $maintenanceWork->owner_decision_at?->translatedFormat('d F Y'),
            'rating' => $maintenanceWork->rating,
            'rating_comment' => $maintenanceWork->rating_comment,
            'owner_name' => $maintenanceWork->owner?->name,
            'created_by' => $maintenanceWork->creator?->name,
            'attachments' => $maintenanceWork->getFormattedAttachments(),
            'created_at' => $maintenanceWork->created_at?->translatedFormat('d F Y'),
        ], 'بيانات تقرير الصيانة جاهزة للطباعة والتنزيل.');
    }
}
