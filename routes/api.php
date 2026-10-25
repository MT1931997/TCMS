<?php

use App\Http\Controllers\Api\Collector\PaymentController;
use App\Http\Controllers\Api\Tenant\MaintenanceRequestController;
use Illuminate\Support\Facades\Route;

// Auth Routes (المصادقة)
Route::prefix('auth')
    ->group(base_path('routes/auth.php'));

// Tenant Maintenance Routes (بوابة وطلبات صيانة المستأجر)
Route::prefix('tenant')
    ->group(base_path('routes/tenant.php'));

// General Manager Module Routes (موديول المشرف العام)
Route::middleware(['auth:sanctum', 'role:general_manager,collection_supervisor,real_estate_supervisor'])
    ->prefix('general-manager')
    ->group(base_path('routes/general-manager.php'));

// Collector Module Routes (موديول المحصل)
Route::middleware(['auth:sanctum', 'role:collector'])
    ->prefix('collector')
    ->group(base_path('routes/collector.php'));

// Owner Module Routes (موديول المالك)
Route::middleware(['auth:sanctum', 'role:owner'])
    ->prefix('owner')
    ->group(base_path('routes/owner.php'));

// Maintenance Supervisor Module Routes (موديول مشرف الصيانة)
Route::middleware(['auth:sanctum', 'role:maintenance_supervisor,general_manager'])
    ->prefix('maintenance-supervisor')
    ->group(base_path('routes/maintenance-supervisor.php'));

// Real Estate Supervisor Module Routes (موديول مشرف العقارات)
Route::middleware(['auth:sanctum', 'role:real_estate_supervisor,general_manager'])
    ->prefix('real-estate-supervisor')
    ->group(base_path('routes/real-estate-supervisor.php'));

// Secure File Attachment Download Routes
Route::middleware('auth:sanctum')
    ->get('payments/{payment}/attachment', [PaymentController::class, 'attachment'])
    ->name('api.payments.attachment');

Route::get('maintenance-requests/{maintenanceRequest}/attachments/{index}', [MaintenanceRequestController::class, 'attachment'])
    ->name('api.maintenance-requests.attachment');
