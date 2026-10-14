<?php

use App\Http\Controllers\Api\GeneralManager\CollectorManagementController;
use App\Http\Controllers\Api\GeneralManager\NoticeRequestController;
use App\Http\Controllers\Api\GeneralManager\OwnerManagementController;
use App\Http\Controllers\Api\GeneralManager\OwnerPaymentController;
use App\Http\Controllers\Api\GeneralManager\PaymentApprovalController;
use App\Http\Controllers\Api\GeneralManager\PaymentController;
use App\Http\Controllers\Api\GeneralManager\SupervisorManagementController;
use App\Http\Controllers\Api\GeneralManager\TenantController;
use App\Http\Controllers\Api\GeneralManager\TenantImportController;
use App\Http\Controllers\Api\GeneralManager\TenantNoteController;
use App\Http\Controllers\Api\GeneralManager\TenantPromiseController;
use Illuminate\Support\Facades\Route;

// إدارة المستخدمين والصلاحيات (المشرفون)
Route::get('supervisors', [SupervisorManagementController::class, 'index'])->name('api.general-manager.supervisors.index');
Route::post('supervisors', [SupervisorManagementController::class, 'store'])->name('api.general-manager.supervisors.store');
Route::get('supervisors/{supervisor}', [SupervisorManagementController::class, 'show'])->name('api.general-manager.supervisors.show');
Route::put('supervisors/{supervisor}', [SupervisorManagementController::class, 'update'])->name('api.general-manager.supervisors.update');
Route::patch('supervisors/{supervisor}/toggle-status', [SupervisorManagementController::class, 'toggleStatus'])->name('api.general-manager.supervisors.toggle-status');
Route::post('supervisors/{supervisor}/toggle-status', [SupervisorManagementController::class, 'toggleStatus'])->name('api.general-manager.supervisors.toggle-status.post');
Route::delete('supervisors/{supervisor}', [SupervisorManagementController::class, 'destroy'])->name('api.general-manager.supervisors.destroy');

// إدارة المستخدمين والصلاحيات (المحصلون)
Route::get('collectors', [CollectorManagementController::class, 'index'])->name('api.general-manager.collectors.index');
Route::post('collectors', [CollectorManagementController::class, 'store'])->name('api.general-manager.collectors.store');
Route::get('collectors/{collector}', [CollectorManagementController::class, 'show'])->name('api.general-manager.collectors.show');
Route::put('collectors/{collector}', [CollectorManagementController::class, 'update'])->name('api.general-manager.collectors.update');
Route::patch('collectors/{collector}/toggle-status', [CollectorManagementController::class, 'toggleStatus'])->name('api.general-manager.collectors.toggle-status');
Route::post('collectors/{collector}/toggle-status', [CollectorManagementController::class, 'toggleStatus'])->name('api.general-manager.collectors.toggle-status.post');
Route::delete('collectors/{collector}', [CollectorManagementController::class, 'destroy'])->name('api.general-manager.collectors.destroy');

// إدارة المستخدمين والصلاحيات (الملاك)
Route::get('owners', [OwnerManagementController::class, 'index'])->name('api.general-manager.owners.index');
Route::post('owners', [OwnerManagementController::class, 'store'])->name('api.general-manager.owners.store');
Route::get('owners/{owner}', [OwnerManagementController::class, 'show'])->name('api.general-manager.owners.show');
Route::put('owners/{owner}', [OwnerManagementController::class, 'update'])->name('api.general-manager.owners.update');
Route::patch('owners/{owner}/toggle-status', [OwnerManagementController::class, 'toggleStatus'])->name('api.general-manager.owners.toggle-status');
Route::post('owners/{owner}/toggle-status', [OwnerManagementController::class, 'toggleStatus'])->name('api.general-manager.owners.toggle-status.post');
Route::delete('owners/{owner}', [OwnerManagementController::class, 'destroy'])->name('api.general-manager.owners.destroy');

// استيراد المستأجرين عبر ملف الإكسل
Route::get('tenants/import/template', [TenantImportController::class, 'template'])->name('api.general-manager.tenants.import.template');
Route::post('tenants/import', [TenantImportController::class, 'import'])->name('api.general-manager.tenants.import');

// إدارة المستأجرين
Route::apiResource('tenants', TenantController::class)->names('api.general-manager.tenants');

// الوعود والمواعيد المتفق عليها
Route::post('tenants/{tenant}/promises', [TenantPromiseController::class, 'store'])->name('api.general-manager.tenants.promises.store');
Route::delete('tenants/{tenant}/promises/{promise}', [TenantPromiseController::class, 'destroy'])->name('api.general-manager.tenants.promises.destroy');

// ملاحظات المستأجر
Route::post('tenants/{tenant}/notes', [TenantNoteController::class, 'store'])->name('api.general-manager.tenants.notes.store');
Route::delete('tenants/{tenant}/notes/{note}', [TenantNoteController::class, 'destroy'])->name('api.general-manager.tenants.notes.destroy');

// سجل السدادات الخاص بالمستأجر
Route::post('tenants/{tenant}/payments', [PaymentController::class, 'store'])->name('api.general-manager.tenants.payments.store');
Route::delete('tenants/{tenant}/payments/{payment}', [PaymentController::class, 'destroy'])->name('api.general-manager.tenants.payments.destroy');

// اعتماد السدادات (بانتظار الاعتماد / جميع السدادات، واعتماد، رفض بالسبب، تعديل، حذف)
Route::get('payments/approvals', [PaymentApprovalController::class, 'index'])->name('api.general-manager.payments.approvals.index');
Route::get('payments/approvals/{payment}', [PaymentApprovalController::class, 'show'])->name('api.general-manager.payments.approvals.show');
Route::post('payments/approvals/{payment}/approve', [PaymentApprovalController::class, 'approve'])->name('api.general-manager.payments.approvals.approve');
Route::post('payments/approvals/{payment}/reject', [PaymentApprovalController::class, 'reject'])->name('api.general-manager.payments.approvals.reject');
Route::put('payments/approvals/{payment}', [PaymentApprovalController::class, 'update'])->name('api.general-manager.payments.approvals.update');
Route::delete('payments/approvals/{payment}', [PaymentApprovalController::class, 'destroy'])->name('api.general-manager.payments.approvals.destroy');

// سدادات الملاك
Route::get('owner-payments', [OwnerPaymentController::class, 'index'])->name('api.general-manager.owner-payments.index');
Route::put('owner-payments/{payment}/notes', [OwnerPaymentController::class, 'updateNotes'])->name('api.general-manager.owner-payments.notes.update');

// طلبات الإشعارات (إدارة طلبات الإشعارات، وإصدارها، واعتماد إثبات التسليم أو الرفض)
Route::get('notice-requests', [NoticeRequestController::class, 'index'])->name('api.general-manager.notice-requests.index');
Route::get('notice-requests/{noticeRequest}', [NoticeRequestController::class, 'show'])->name('api.general-manager.notice-requests.show');
Route::post('notice-requests/{noticeRequest}/issue', [NoticeRequestController::class, 'issue'])->name('api.general-manager.notice-requests.issue');
Route::post('notice-requests/{noticeRequest}/approve-delivery', [NoticeRequestController::class, 'approveDelivery'])->name('api.general-manager.notice-requests.approve-delivery');
Route::post('notice-requests/{noticeRequest}/reject', [NoticeRequestController::class, 'reject'])->name('api.general-manager.notice-requests.reject');
