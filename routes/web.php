<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::middleware('guest')->group(function () {
    Route::get('/auth/login', [SsoController::class, 'login'])->name('login');
    Route::get('/auth/callback', [SsoController::class, 'callback'])->name('auth.callback');
});

Route::middleware(['auth', 'sso.session'])->group(function () {
    Route::get('/office', DashboardController::class)->name('office.home');
    Route::post('/logout', [SsoController::class, 'logout'])->name('auth.logout');
});
