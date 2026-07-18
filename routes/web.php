<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::middleware('guest')->group(function () {
    Route::get('/auth/login', [SsoController::class, 'login'])->name('login');
    Route::get('/auth/callback', [SsoController::class, 'callback'])->name('auth.callback');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('auth.callback.legacy');
});

Route::middleware(['auth', 'sso.session'])->group(function () {
    Route::get('/office', DashboardController::class)->name('office.home');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/issued', [DocumentController::class, 'issued'])->name('documents.issued');
    Route::post('/documents/{document}/void', [DocumentController::class, 'void'])->name('documents.void');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/document-types/preview', [DocumentTypeController::class, 'preview'])->name('document-types.preview');
    Route::patch('/document-types/{document_type}/toggle', [DocumentTypeController::class, 'toggle'])->name('document-types.toggle');
    Route::resource('document-types', DocumentTypeController::class)->except('show');
    Route::get('/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->name('quotations.preview');
    Route::post('/quotations/{quotation}/complete', [QuotationController::class, 'complete'])->name('quotations.complete');
    Route::post('/quotations/{quotation}/submit', [QuotationController::class, 'submit'])->name('quotations.submit');
    Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::post('/quotations/{quotation}/void', [QuotationController::class, 'void'])->name('quotations.void');
    Route::resource('quotations', QuotationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/logout', [SsoController::class, 'logout'])->name('auth.logout');
});
