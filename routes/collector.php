<?php

use App\Http\Controllers\Api\Collector\NoticeRequestController;
use App\Http\Controllers\Api\Collector\PaymentController;
use App\Http\Controllers\Api\Collector\TenantController;
use App\Http\Controllers\Api\Collector\TenantNoteController;
use App\Http\Controllers\Api\Collector\TenantPromiseController;
use Illuminate\Support\Facades\Route;

// مستأجروني (عرض المستأجرين الخاصين بالمحصل وتفاصيلهم)
Route::get('tenants', [TenantController::class, 'index'])->name('api.collector.tenants.index');
Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('api.collector.tenants.show');

// إضافة موعد وملاحظة
Route::post('tenants/{tenant}/promises', [TenantPromiseController::class, 'store'])->name('api.collector.tenants.promises.store');
Route::post('tenants/{tenant}/notes', [TenantNoteController::class, 'store'])->name('api.collector.tenants.notes.store');

// سداداتي ورفع سداد جديد
Route::get('payments', [PaymentController::class, 'index'])->name('api.collector.payments.index');
Route::post('payments', [PaymentController::class, 'storeDirect'])->name('api.collector.payments.store-direct');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('api.collector.payments.show');
Route::get('payments/{payment}/attachment', [PaymentController::class, 'attachment'])->name('api.collector.payments.attachment');
Route::post('tenants/{tenant}/payments', [PaymentController::class, 'store'])->name('api.collector.tenants.payments.store');

// طلبات الإشعارات (طلباتي، طلب إشعار جديد، إرفاق إثبات التسليم)
Route::get('notice-requests', [NoticeRequestController::class, 'index'])->name('api.collector.notice-requests.index');
Route::post('notice-requests', [NoticeRequestController::class, 'store'])->name('api.collector.notice-requests.store');
Route::get('notice-requests/{noticeRequest}', [NoticeRequestController::class, 'show'])->name('api.collector.notice-requests.show');
Route::post('notice-requests/{noticeRequest}/delivery-proof', [NoticeRequestController::class, 'submitDeliveryProof'])->name('api.collector.notice-requests.delivery-proof');
