<?php

use App\Http\Controllers\Api\Admin\AppointmentController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [TokenController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.auth.token.store');

Route::middleware(['auth:sanctum', 'can:access-admin'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ]);
    })->middleware('abilities:appointments:read')->name('api.user');

    Route::delete('/auth/token', [TokenController::class, 'destroy'])
        ->middleware('abilities:token:revoke')
        ->name('api.auth.token.destroy');

    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->middleware('abilities:appointments:read')
        ->name('api.appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('abilities:appointments:write')
        ->name('api.appointments.store');
    Route::post('/appointment', [AppointmentController::class, 'store'])
        ->middleware('abilities:appointments:write')
        ->name('api.appointment.store');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->middleware('abilities:appointments:read')
        ->name('api.appointments.show');
    Route::match(['put', 'patch'], '/appointments/{appointment}', [AppointmentController::class, 'update'])
        ->middleware('abilities:appointments:write')
        ->name('api.appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
        ->middleware('abilities:appointments:delete')
        ->name('api.appointments.destroy');
});
