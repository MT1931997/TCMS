<?php

use App\Http\Controllers\Api\RealEstateSupervisor\PropertyController;
use Illuminate\Support\Facades\Route;

// إدارة الأملاك والعقود (العقارات)
Route::get('properties/{property}/contracts/options', [PropertyController::class, 'contractOptions'])->name('api.real-estate-supervisor.properties.contracts.options');
Route::post('properties/{property}/contracts', [PropertyController::class, 'storeContract'])->name('api.real-estate-supervisor.properties.contracts.store');

Route::scopeBindings()->group(function () {
    Route::put('properties/{property}/contracts/{contract}', [PropertyController::class, 'updateContract'])->name('api.real-estate-supervisor.properties.contracts.update');
    Route::post('properties/{property}/contracts/{contract}/terminate', [PropertyController::class, 'terminateContract'])->name('api.real-estate-supervisor.properties.contracts.terminate');
    Route::post('properties/{property}/contracts/{contract}/renew', [PropertyController::class, 'renewContract'])->name('api.real-estate-supervisor.properties.contracts.renew');
});

Route::apiResource('properties', PropertyController::class)->names('api.real-estate-supervisor.properties');
