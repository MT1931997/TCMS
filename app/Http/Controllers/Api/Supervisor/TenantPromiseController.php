<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\StorePromiseRequest;
use App\Http\Resources\TenantPromiseResource;
use App\Models\TenantInfo;
use App\Models\TenantPromise;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantPromiseController extends Controller
{
    /**
     * Store a newly created promise / appointment for the tenant.
     */
    public function store(StorePromiseRequest $request, TenantInfo $tenant): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';

        $promise = $tenant->promises()->create($validated);
        $promise->load('user');

        return ApiResponse::success(
            new TenantPromiseResource($promise),
            'تمت إضافة الموعد بنجاح.',
            201
        );
    }

    /**
     * Delete a promise.
     */
    public function destroy(TenantInfo $tenant, TenantPromise $promise): JsonResponse
    {
        if ($promise->tenant_id !== $tenant->id) {
            return ApiResponse::error('الموعد غير مرتبط بهذا المستأجر.', 404);
        }

        $promise->delete();

        return ApiResponse::success(null, 'تم حذف الموعد بنجاح.');
    }
}
