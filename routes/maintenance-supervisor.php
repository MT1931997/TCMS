<?php

use App\Http\Controllers\Api\MaintenanceSupervisor\MaintenanceRequestController;
use App\Http\Controllers\Api\MaintenanceSupervisor\MaintenanceWorkController;
use Illuminate\Support\Facades\Route;

// طلبات صيانة المستأجرين (إدارة واعتماد ورفض وتعديل وحذف طلبات الصيانة)
Route::get('maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('api.maintenance-supervisor.maintenance-requests.index');
Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('api.maintenance-supervisor.maintenance-requests.show');
Route::post('maintenance-requests/{maintenanceRequest}/approve', [MaintenanceRequestController::class, 'approve'])->name('api.maintenance-supervisor.maintenance-requests.approve');
Route::post('maintenance-requests/{maintenanceRequest}/reject', [MaintenanceRequestController::class, 'reject'])->name('api.maintenance-supervisor.maintenance-requests.reject');
Route::put('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('api.maintenance-supervisor.maintenance-requests.update');
Route::delete('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'destroy'])->name('api.maintenance-supervisor.maintenance-requests.destroy');

// أعمال الصيانة (التقارير المرسلة، إرسال تقرير صيانة جديد، تفاصيل، تعديل، حذف، وتصدير الصيانة المعتمدة)
Route::get('maintenance-works/export-pdf', [MaintenanceWorkController::class, 'exportPdf'])->name('api.maintenance-supervisor.maintenance-works.export-pdf');
Route::apiResource('maintenance-works', MaintenanceWorkController::class)->names('api.maintenance-supervisor.maintenance-works');
