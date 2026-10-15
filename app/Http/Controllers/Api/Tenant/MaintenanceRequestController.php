<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\MaintenanceRequestSupervisorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMaintenanceRequestRequest;
use App\Http\Requests\Tenant\TrackMaintenanceRequestRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\TenantInfo;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class MaintenanceRequestController extends Controller
{
    /**
     * Submit a new maintenance request from the tenant portal.
     */
    public function store(StoreMaintenanceRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check if there is an existing registered tenant matching national_id
        $tenant = TenantInfo::where('national_id', $validated['national_id'])->first();
        if (! $tenant) {
            $userWithNationalId = User::where('national_id', $validated['national_id'])->with('tenantInfo')->first();
            $tenant = $userWithNationalId?->tenantInfo;
        }

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
                    $path = $file->store('maintenance_requests', 'public');
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
        $query = MaintenanceRequest::query()->with(['tenant.user', 'owner']);

        $nationalId = $request->input('national_id');
        $phone = $request->input('phone');
        $ticketNumber = $request->input('ticket_number');

        $query->where(function ($q) use ($nationalId, $phone, $ticketNumber) {
            if (! empty($ticketNumber)) {
                $q->where('ticket_number', $ticketNumber);
            }

            if (! empty($nationalId) && ! empty($phone)) {
                $q->where(function ($sub) use ($nationalId, $phone) {
                    $sub->where('national_id', $nationalId)
                        ->orWhere('phone', $phone);
                });
            } elseif (! empty($nationalId)) {
                $q->where('national_id', $nationalId);
            } elseif (! empty($phone)) {
                $q->where('phone', $phone);
            }
        });

        $requests = $query->latest('id')->get();

        return ApiResponse::success(
            MaintenanceRequestResource::collection($requests),
            'تم جلب طلبات الصيانة بنجاح.'
        );
    }

    /**
     * Show single maintenance request by ticket number or ID.
     */
    public function show(string $ticketNumber): JsonResponse
    {
        $request = MaintenanceRequest::with(['tenant.user', 'owner', 'reviewer'])
            ->where('ticket_number', $ticketNumber)
            ->orWhere('id', $ticketNumber)
            ->first();

        if (! $request) {
            return ApiResponse::error('طلب الصيانة غير موجود.', 404);
        }

        return ApiResponse::success(
            new MaintenanceRequestResource($request),
            'تفاصيل طلب الصيانة.'
        );
    }
}
