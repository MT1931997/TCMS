<?php

namespace App\Http\Controllers\Api\Collector;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\StoreTenantNoteRequest;
use App\Http\Resources\TenantNoteResource;
use App\Models\TenantInfo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantNoteController extends Controller
{
    /**
     * Store a newly created note for the tenant by collector.
     */
    public function store(StoreTenantNoteRequest $request, TenantInfo $tenant): JsonResponse
    {
        if ($tenant->collector_id !== $request->user()->id && ! $request->user()->isGeneralManager()) {
            return ApiResponse::error('ليس لديك صلاحية لإضافة ملاحظة لهذا المستأجر.', 403);
        }

        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $note = $tenant->notesList()->create($validated);
        $note->load('user');

        return ApiResponse::success(
            new TenantNoteResource($note),
            'تمت إضافة الملاحظة بنجاح.',
            201
        );
    }
}
