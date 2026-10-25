<?php

use App\Http\Controllers\Api\Tenant\MaintenanceRequestController;
use Illuminate\Support\Facades\Route;

// Tenant Maintenance Routes (بوابة وطلبات صيانة المستأجر)
Route::prefix('maintenance-requests')
    ->middleware('throttle:10,1')
    ->group(function () {
        Route::post('/', [MaintenanceRequestController::class, 'store'])->name('api.tenant.maintenance-requests.store');
        Route::get('track', [MaintenanceRequestController::class, 'track'])->name('api.tenant.maintenance-requests.track');
        Route::get('{ticketNumber}', [MaintenanceRequestController::class, 'show'])->name('api.tenant.maintenance-requests.show');
    });
