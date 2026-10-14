<?php

namespace App\Http\Controllers\Api\GeneralManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralManager\ImportTenantsRequest;
use App\Services\TenantImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantImportController extends Controller
{
    /**
     * Import tenants from an uploaded Excel or CSV file.
     */
    public function import(ImportTenantsRequest $request, TenantImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'), $request->user());

        $message = "تم استيراد {$result['imported']} مستأجر جديد";
        if ($result['updated'] > 0) {
            $message .= " وتحديث بيانات {$result['updated']} مستأجر";
        }
        $message .= ' بنجاح.';

        return ApiResponse::success($result, $message);
    }

    /**
     * Download a sample Excel template for tenants import.
     */
    public function template(TenantImportService $importService): StreamedResponse
    {
        return $importService->downloadTemplate();
    }
}
