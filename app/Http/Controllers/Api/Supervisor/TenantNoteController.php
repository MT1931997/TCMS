<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\StoreTenantNoteRequest;
use App\Http\Resources\TenantNoteResource;
use App\Models\TenantInfo;
use App\Models\TenantNote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantNoteController extends Controller
{
    /**
     * Store a newly created note for the tenant.
     */
    public function store(StoreTenantNoteRequest $request, TenantInfo $tenant): JsonResponse
    {
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

    /**
     * Delete a note.
     */
    public function destroy(TenantInfo $tenant, TenantNote $note): JsonResponse
    {
        if ($note->tenant_id !== $tenant->id) {
            return ApiResponse::error('الملاحظة غير مرتبطة بهذا المستأجر.', 404);
        }

        $note->delete();

        return ApiResponse::success(null, 'تم حذف الملاحظة بنجاح.');
    }
}
