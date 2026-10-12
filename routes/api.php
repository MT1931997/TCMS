<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Collector\NoticeRequestController as CollectorNoticeRequestController;
use App\Http\Controllers\Api\Collector\PaymentController as CollectorPaymentController;
use App\Http\Controllers\Api\Collector\TenantController as CollectorTenantController;
use App\Http\Controllers\Api\Collector\TenantNoteController as CollectorTenantNoteController;
use App\Http\Controllers\Api\Collector\TenantPromiseController as CollectorTenantPromiseController;
use App\Http\Controllers\Api\Owner\OverviewController as OwnerOverviewController;
use App\Http\Controllers\Api\Owner\PaymentController as OwnerPortalPaymentController;
use App\Http\Controllers\Api\Owner\PropertyController as OwnerPropertyController;
use App\Http\Controllers\Api\Supervisor\NoticeRequestController as SupervisorNoticeRequestController;
use App\Http\Controllers\Api\Supervisor\OwnerPaymentController;
use App\Http\Controllers\Api\Supervisor\PaymentApprovalController;
use App\Http\Controllers\Api\Supervisor\PaymentController;
use App\Http\Controllers\Api\Supervisor\TenantController;
use App\Http\Controllers\Api\Supervisor\TenantImportController;
use App\Http\Controllers\Api\Supervisor\TenantNoteController;
use App\Http\Controllers\Api\Supervisor\TenantPromiseController;
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

// Supervisor Module Routes (موديول المشرف)
Route::middleware('auth:sanctum')->prefix('supervisor')->group(function () {
    // استيراد المستأجرين عبر ملف الإكسل
    Route::get('tenants/import/template', [TenantImportController::class, 'template'])->name('api.supervisor.tenants.import.template');
    Route::post('tenants/import', [TenantImportController::class, 'import'])->name('api.supervisor.tenants.import');

    // إدارة المستأجرين
    Route::apiResource('tenants', TenantController::class)->names('api.supervisor.tenants');

    // الوعود والمواعيد المتفق عليها
    Route::post('tenants/{tenant}/promises', [TenantPromiseController::class, 'store'])->name('api.supervisor.tenants.promises.store');
    Route::delete('tenants/{tenant}/promises/{promise}', [TenantPromiseController::class, 'destroy'])->name('api.supervisor.tenants.promises.destroy');

    // ملاحظات المستأجر
    Route::post('tenants/{tenant}/notes', [TenantNoteController::class, 'store'])->name('api.supervisor.tenants.notes.store');
    Route::delete('tenants/{tenant}/notes/{note}', [TenantNoteController::class, 'destroy'])->name('api.supervisor.tenants.notes.destroy');

    // سجل السدادات الخاص بالمستأجر
    Route::post('tenants/{tenant}/payments', [PaymentController::class, 'store'])->name('api.supervisor.tenants.payments.store');
    Route::delete('tenants/{tenant}/payments/{payment}', [PaymentController::class, 'destroy'])->name('api.supervisor.tenants.payments.destroy');

    // اعتماد السدادات (بانتظار الاعتماد / جميع السدادات، واعتماد، رفض بالسبب، تعديل، حذف)
    Route::get('payments/approvals', [PaymentApprovalController::class, 'index'])->name('api.supervisor.payments.approvals.index');
    Route::get('payments/approvals/{payment}', [PaymentApprovalController::class, 'show'])->name('api.supervisor.payments.approvals.show');
    Route::post('payments/approvals/{payment}/approve', [PaymentApprovalController::class, 'approve'])->name('api.supervisor.payments.approvals.approve');
    Route::post('payments/approvals/{payment}/reject', [PaymentApprovalController::class, 'reject'])->name('api.supervisor.payments.approvals.reject');
    Route::put('payments/approvals/{payment}', [PaymentApprovalController::class, 'update'])->name('api.supervisor.payments.approvals.update');
    Route::delete('payments/approvals/{payment}', [PaymentApprovalController::class, 'destroy'])->name('api.supervisor.payments.approvals.destroy');

    // سدادات الملاك
    Route::get('owner-payments', [OwnerPaymentController::class, 'index'])->name('api.supervisor.owner-payments.index');
    Route::put('owner-payments/{payment}/notes', [OwnerPaymentController::class, 'updateNotes'])->name('api.supervisor.owner-payments.notes.update');

    // طلبات الإشعارات (إدارة طلبات الإشعارات، وإصدارها، واعتماد إثبات التسليم أو الرفض)
    Route::get('notice-requests', [SupervisorNoticeRequestController::class, 'index'])->name('api.supervisor.notice-requests.index');
    Route::get('notice-requests/{noticeRequest}', [SupervisorNoticeRequestController::class, 'show'])->name('api.supervisor.notice-requests.show');
    Route::post('notice-requests/{noticeRequest}/issue', [SupervisorNoticeRequestController::class, 'issue'])->name('api.supervisor.notice-requests.issue');
    Route::post('notice-requests/{noticeRequest}/approve-delivery', [SupervisorNoticeRequestController::class, 'approveDelivery'])->name('api.supervisor.notice-requests.approve-delivery');
    Route::post('notice-requests/{noticeRequest}/reject', [SupervisorNoticeRequestController::class, 'reject'])->name('api.supervisor.notice-requests.reject');
});

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
});
