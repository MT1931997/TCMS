<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Collector\NoticeRequestController as CollectorNoticeRequestController;
use App\Http\Controllers\Api\Collector\PaymentController as CollectorPaymentController;
use App\Http\Controllers\Api\Collector\TenantController as CollectorTenantController;
use App\Http\Controllers\Api\Collector\TenantNoteController as CollectorTenantNoteController;
use App\Http\Controllers\Api\Collector\TenantPromiseController as CollectorTenantPromiseController;
use App\Http\Controllers\Api\MaintenanceSupervisor\MaintenanceRequestController as MaintenanceSupervisorRequestController;
use App\Http\Controllers\Api\MaintenanceSupervisor\MaintenanceWorkController as MaintenanceSupervisorWorkController;
use App\Http\Controllers\Api\Owner\MaintenanceRequestController as OwnerMaintenanceRequestController;
use App\Http\Controllers\Api\Owner\MaintenanceWorkController as OwnerMaintenanceWorkController;
use App\Http\Controllers\Api\Owner\OverviewController as OwnerOverviewController;
use App\Http\Controllers\Api\Owner\PaymentController as OwnerPortalPaymentController;
use App\Http\Controllers\Api\Owner\PropertyController as OwnerPropertyController;
use App\Http\Controllers\Api\Tenant\MaintenanceRequestController as TenantMaintenanceRequestController;
use Illuminate\Support\Facades\Route;

// Auth Routes (المصادقة)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::get('roles', [AuthController::class, 'roles'])->name('api.auth.roles');
    Route::get('users-by-type', [AuthController::class, 'usersByType'])->name('api.auth.users-by-type');

    // Protected Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// Tenant Maintenance Routes (بوابة وطلبات صيانة المستأجر)
Route::prefix('tenant/maintenance-requests')->group(function () {
    Route::post('/', [TenantMaintenanceRequestController::class, 'store'])->name('api.tenant.maintenance-requests.store');
    Route::get('track', [TenantMaintenanceRequestController::class, 'track'])->name('api.tenant.maintenance-requests.track');
    Route::get('{ticketNumber}', [TenantMaintenanceRequestController::class, 'show'])->name('api.tenant.maintenance-requests.show');
});

// General Manager Module Routes (موديول المشرف العام)
Route::middleware('auth:sanctum')
    ->prefix('general-manager')
    ->group(base_path('routes/general-manager.php'));

// Collector Module Routes (موديول المحصل)
Route::middleware('auth:sanctum')->prefix('collector')->group(function () {
    // مستأجروني (عرض المستأجرين الخاصين بالمحصل وتفاصيلهم)
    Route::get('tenants', [CollectorTenantController::class, 'index'])->name('api.collector.tenants.index');
    Route::get('tenants/{tenant}', [CollectorTenantController::class, 'show'])->name('api.collector.tenants.show');

    // إضافة موعد وملاحظة
    Route::post('tenants/{tenant}/promises', [CollectorTenantPromiseController::class, 'store'])->name('api.collector.tenants.promises.store');
    Route::post('tenants/{tenant}/notes', [CollectorTenantNoteController::class, 'store'])->name('api.collector.tenants.notes.store');

    // سداداتي ورفع سداد جديد
    Route::get('payments', [CollectorPaymentController::class, 'index'])->name('api.collector.payments.index');
    Route::post('payments', [CollectorPaymentController::class, 'storeDirect'])->name('api.collector.payments.store-direct');
    Route::get('payments/{payment}', [CollectorPaymentController::class, 'show'])->name('api.collector.payments.show');
    Route::post('tenants/{tenant}/payments', [CollectorPaymentController::class, 'store'])->name('api.collector.tenants.payments.store');

    // طلبات الإشعارات (طلباتي، طلب إشعار جديد، إرفاق إثبات التسليم)
    Route::get('notice-requests', [CollectorNoticeRequestController::class, 'index'])->name('api.collector.notice-requests.index');
    Route::post('notice-requests', [CollectorNoticeRequestController::class, 'store'])->name('api.collector.notice-requests.store');
    Route::get('notice-requests/{noticeRequest}', [CollectorNoticeRequestController::class, 'show'])->name('api.collector.notice-requests.show');
    Route::post('notice-requests/{noticeRequest}/delivery-proof', [CollectorNoticeRequestController::class, 'submitDeliveryProof'])->name('api.collector.notice-requests.delivery-proof');
});

// Owner Module Routes (موديول المالك)
Route::middleware('auth:sanctum')->prefix('owner')->group(function () {
    // نظرة عامة (لوحة معلومات المالك)
    Route::get('overview', [OwnerOverviewController::class, 'index'])->name('api.owner.overview');

    // سدادات وحداتي وملاحظات السداد
    Route::get('payments', [OwnerPortalPaymentController::class, 'index'])->name('api.owner.payments.index');
    Route::get('payments/{payment}', [OwnerPortalPaymentController::class, 'show'])->name('api.owner.payments.show');
    Route::get('payments/{payment}/notes', [OwnerPortalPaymentController::class, 'notes'])->name('api.owner.payments.notes');
    Route::post('payments/{payment}/notes', [OwnerPortalPaymentController::class, 'storeNote'])->name('api.owner.payments.notes.store');

    // إدارة أملاكي
    Route::get('properties', [OwnerPropertyController::class, 'index'])->name('api.owner.properties.index');

    // أعمال وطلبات الصيانة (طلبات الصيانة المحولة وسجل القرارات)
    Route::get('maintenance-requests', [OwnerMaintenanceRequestController::class, 'index'])->name('api.owner.maintenance-requests.index');
    Route::get('maintenance-requests/{maintenanceRequest}', [OwnerMaintenanceRequestController::class, 'show'])->name('api.owner.maintenance-requests.show');
    Route::post('maintenance-requests/{maintenanceRequest}/approve', [OwnerMaintenanceRequestController::class, 'approve'])->name('api.owner.maintenance-requests.approve');
    Route::post('maintenance-requests/{maintenanceRequest}/reject', [OwnerMaintenanceRequestController::class, 'reject'])->name('api.owner.maintenance-requests.reject');

    // أعمال الصيانة (بانتظار قراركم، سجل القرارات، قبول الصيانة، رفض الصيانة، قيّم جودة الصيانة، وتصدير الصيانة المعتمدة)
    Route::get('maintenance-works/export-approved-pdf', [OwnerMaintenanceWorkController::class, 'exportApprovedPdf'])->name('api.owner.maintenance-works.export-approved-pdf');
    Route::get('maintenance-works', [OwnerMaintenanceWorkController::class, 'index'])->name('api.owner.maintenance-works.index');
    Route::get('maintenance-works/{maintenanceWork}', [OwnerMaintenanceWorkController::class, 'show'])->name('api.owner.maintenance-works.show');
    Route::post('maintenance-works/{maintenanceWork}/approve', [OwnerMaintenanceWorkController::class, 'approve'])->name('api.owner.maintenance-works.approve');
    Route::post('maintenance-works/{maintenanceWork}/reject', [OwnerMaintenanceWorkController::class, 'reject'])->name('api.owner.maintenance-works.reject');
    Route::post('maintenance-works/{maintenanceWork}/rate', [OwnerMaintenanceWorkController::class, 'rate'])->name('api.owner.maintenance-works.rate');
    Route::get('maintenance-works/{maintenanceWork}/pdf', [OwnerMaintenanceWorkController::class, 'pdf'])->name('api.owner.maintenance-works.pdf');
});

// Maintenance Supervisor Module Routes (موديول مشرف الصيانة)
Route::middleware('auth:sanctum')->prefix('maintenance-supervisor')->group(function () {
    // طلبات صيانة المستأجرين (إدارة واعتماد ورفض وتعديل وحذف طلبات الصيانة)
    Route::get('maintenance-requests', [MaintenanceSupervisorRequestController::class, 'index'])->name('api.maintenance-supervisor.maintenance-requests.index');
    Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceSupervisorRequestController::class, 'show'])->name('api.maintenance-supervisor.maintenance-requests.show');
    Route::post('maintenance-requests/{maintenanceRequest}/approve', [MaintenanceSupervisorRequestController::class, 'approve'])->name('api.maintenance-supervisor.maintenance-requests.approve');
    Route::post('maintenance-requests/{maintenanceRequest}/reject', [MaintenanceSupervisorRequestController::class, 'reject'])->name('api.maintenance-supervisor.maintenance-requests.reject');
    Route::put('maintenance-requests/{maintenanceRequest}', [MaintenanceSupervisorRequestController::class, 'update'])->name('api.maintenance-supervisor.maintenance-requests.update');
    Route::delete('maintenance-requests/{maintenanceRequest}', [MaintenanceSupervisorRequestController::class, 'destroy'])->name('api.maintenance-supervisor.maintenance-requests.destroy');

    // أعمال الصيانة (التقارير المرسلة، إرسال تقرير صيانة جديد، تفاصيل، تعديل، حذف، وتصدير الصيانة المعتمدة)
    Route::get('maintenance-works/export-pdf', [MaintenanceSupervisorWorkController::class, 'exportPdf'])->name('api.maintenance-supervisor.maintenance-works.export-pdf');
    Route::apiResource('maintenance-works', MaintenanceSupervisorWorkController::class)->names('api.maintenance-supervisor.maintenance-works');
});
