<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMaintenanceRequestRequest;
use App\Http\Requests\Tenant\TrackMaintenanceRequestRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Http\Resources\PublicMaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceRequestController extends Controller
{
    /**
     * Submit a new maintenance request from the tenant portal.
     */
    public function store(StoreMaintenanceRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // الربط بيحصل لو الرقم القومي + الموبايل + تاريخ الميلاد كلهم مطابقين
        // غير كده الطلب بتسجل من غير ربط، والمشرف يربطه بإيده بعد المراجعة
        $tenant = TenantInfo::where('national_id', $validated['national_id'])
            ->whereDate('birth_date', $validated['birth_date'])
            ->whereHas('user', fn ($q) => $q->where('phone', $validated['phone']))
            ->first();

        $tenantId = $tenant?->id;
        $ownerId = $tenant?->owner_id;

        // Process attachments
        $savedAttachments = [];
        if ($request->hasFile('attachments')) {
            $files = $request->file('attachments');
            if ($files instanceof UploadedFile) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $path = $file->store('maintenance_requests', 'local');
                    $savedAttachments[] = [
                        'path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'file_type' => str_starts_with($file->getClientMimeType(), 'video/') ? 'video' : 'image',
                    ];
                }
            }
        }

        $ticketNumber = MaintenanceRequest::generateTicketNumber();

        $maintenanceRequest = MaintenanceRequest::create([
            'ticket_number' => $ticketNumber,
            'tenant_name' => $validated['tenant_name'],
            'national_id' => $validated['national_id'],
            'birth_date' => $validated['birth_date'],
            'phone' => $validated['phone'],
            'property_unit' => $validated['property_unit'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'address' => $validated['address'] ?? null,
            'attachments' => ! empty($savedAttachments) ? $savedAttachments : null,
            'supervisor_status' => MaintenanceRequestSupervisorStatus::PendingReview->value,
            'owner_status' => null,
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
        ]);

        $maintenanceRequest->load(['tenant.user', 'owner']);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest),
            'تم رفع طلب الصيانة بنجاح وهو بانتظار مراجعة المشرف.',
            201
        );
    }

    /**
     * Track submitted maintenance requests by national ID, phone number, or ticket number.
     */
    public function track(TrackMaintenanceRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = MaintenanceRequest::query()
            ->where('national_id', $validated['national_id']);

        if (! empty($validated['phone'])) {
            $query->where('phone', $validated['phone']);
        }

        if (! empty($validated['ticket_number'])) {
            $query->where('ticket_number', $validated['ticket_number']);
        }

        $requests = $query->latest('id')->get();

        return ApiResponse::success(
            PublicMaintenanceRequestResource::collection($requests),
            'تم جلب طلبات الصيانة بنجاح.'
        );
    }

    /**
     * Show single maintenance request by ticket number and national ID.
     */
    public function show(TrackMaintenanceRequestRequest $request, string $ticketNumber): JsonResponse
    {
        $maintenanceRequest = MaintenanceRequest::where('ticket_number', $ticketNumber)
            ->where('national_id', $request->validated('national_id'))
            ->first();

        return $maintenanceRequest
            ? ApiResponse::success(new PublicMaintenanceRequestResource($maintenanceRequest))
            : ApiResponse::error('طلب الصيانة غير موجود.', 404);
    }

    /**
     * Download or view maintenance request attachment securely.
     */
    public function attachment(Request $request, MaintenanceRequest $maintenanceRequest, int $index): Response
    {
        $user = $request->user('sanctum');

        $isAuthorized = false;
        if ($user && Gate::forUser($user)->allows('viewAttachment', $maintenanceRequest)) {
            $isAuthorized = true;
        } elseif ($request->filled('ticket_number') && $request->input('ticket_number') === $maintenanceRequest->ticket_number) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            return ApiResponse::error('ليس لديك صلاحية لعرض هذا المرفق.', 403);
        }

        $attachments = is_array($maintenanceRequest->attachments) ? array_values($maintenanceRequest->attachments) : [];
        $attachment = $attachments[$index] ?? null;

        if (! $attachment) {
            return ApiResponse::error('المرفق غير موجود.', 404);
        }

        $path = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;
        $fileName = is_array($attachment) ? ($attachment['file_name'] ?? basename($path)) : basename($path);

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return ApiResponse::error('المرفق غير موجود.', 404);
        }

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($path, $fileName);
        }

        return Storage::disk('local')->response($path, $fileName);
    }
}
