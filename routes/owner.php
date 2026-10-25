<?php

use App\Http\Controllers\Api\Owner\MaintenanceRequestController;
use App\Http\Controllers\Api\Owner\MaintenanceWorkController;
use App\Http\Controllers\Api\Owner\OverviewController;
use App\Http\Controllers\Api\Owner\PaymentController;
use App\Http\Controllers\Api\Owner\PropertyController;
use Illuminate\Support\Facades\Route;

// نظرة عامة (لوحة معلومات المالك)
Route::get('overview', [OverviewController::class, 'index'])->name('api.owner.overview');

// سدادات وحداتي وملاحظات السداد
Route::get('payments', [PaymentController::class, 'index'])->name('api.owner.payments.index');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('api.owner.payments.show');
Route::get('payments/{payment}/notes', [PaymentController::class, 'notes'])->name('api.owner.payments.notes');
Route::post('payments/{payment}/notes', [PaymentController::class, 'storeNote'])->name('api.owner.payments.notes.store');

// إدارة أملاكي
Route::get('properties', [PropertyController::class, 'index'])->name('api.owner.properties.index');

// أعمال وطلبات الصيانة (طلبات الصيانة المحولة وسجل القرارات)
Route::get('maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('api.owner.maintenance-requests.index');
Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('api.owner.maintenance-requests.show');
Route::post('maintenance-requests/{maintenanceRequest}/approve', [MaintenanceRequestController::class, 'approve'])->name('api.owner.maintenance-requests.approve');
Route::post('maintenance-requests/{maintenanceRequest}/reject', [MaintenanceRequestController::class, 'reject'])->name('api.owner.maintenance-requests.reject');

// أعمال الصيانة (بانتظار قراركم، سجل القرارات، قبول الصيانة، رفض الصيانة، قيّم جودة الصيانة، وتصدير الصيانة المعتمدة)
Route::get('maintenance-works/export-approved-pdf', [MaintenanceWorkController::class, 'exportApprovedPdf'])->name('api.owner.maintenance-works.export-approved-pdf');
Route::get('maintenance-works', [MaintenanceWorkController::class, 'index'])->name('api.owner.maintenance-works.index');
Route::get('maintenance-works/{maintenanceWork}', [MaintenanceWorkController::class, 'show'])->name('api.owner.maintenance-works.show');
Route::post('maintenance-works/{maintenanceWork}/approve', [MaintenanceWorkController::class, 'approve'])->name('api.owner.maintenance-works.approve');
Route::post('maintenance-works/{maintenanceWork}/reject', [MaintenanceWorkController::class, 'reject'])->name('api.owner.maintenance-works.reject');
Route::post('maintenance-works/{maintenanceWork}/rate', [MaintenanceWorkController::class, 'rate'])->name('api.owner.maintenance-works.rate');
Route::get('maintenance-works/{maintenanceWork}/pdf', [MaintenanceWorkController::class, 'pdf'])->name('api.owner.maintenance-works.pdf');
