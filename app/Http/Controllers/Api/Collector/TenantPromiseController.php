<?php

namespace App\Http\Controllers\Api\Collector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\StorePromiseRequest;
use App\Http\Resources\TenantPromiseResource;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantPromiseController extends Controller
{
    /**
     * Store a newly created promise / appointment for the tenant by collector.
     */
    public function store(StorePromiseRequest $request, TenantInfo $tenant): JsonResponse
    {
        if ($tenant->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لإضافة موعد لهذا المستأجر.', 403);
        }

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
}
