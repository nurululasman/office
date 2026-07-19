<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::middleware('guest')->group(function () {
    Route::get('/auth/login', [SsoController::class, 'login'])->middleware('throttle:sso-login')->name('login');
    Route::get('/auth/callback', [SsoController::class, 'callback'])->middleware('throttle:sso-callback')->name('auth.callback');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->middleware('throttle:sso-callback')->name('auth.callback.legacy');
});

Route::middleware(['auth', 'sso.session'])->group(function () {
    Route::get('/office', DashboardController::class)->name('office.home');
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
    Route::resource('roles', RoleController::class)->except('show');
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:office-mutation')->name('documents.store');
    Route::get('/documents/{document}/issued', [DocumentController::class, 'issued'])->name('documents.issued');
    Route::post('/documents/{document}/void', [DocumentController::class, 'void'])->middleware('throttle:office-mutation')->name('documents.void');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/document-types/preview', [DocumentTypeController::class, 'preview'])->name('document-types.preview');
    Route::patch('/document-types/{document_type}/toggle', [DocumentTypeController::class, 'toggle'])->name('document-types.toggle');
    Route::resource('document-types', DocumentTypeController::class)->except('show');
    Route::get('/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->middleware('throttle:office-preview')->name('quotations.preview');
    Route::get('/quotations/{quotation}/pdf/preview', [QuotationController::class, 'previewPdf'])->middleware('throttle:office-preview')->name('quotations.pdf.preview');
    Route::get('/quotations/{quotation}/pdf/download', [QuotationController::class, 'downloadPdf'])->middleware('throttle:office-preview')->name('quotations.pdf.download');
    Route::post('/quotations/{quotation}/complete', [QuotationController::class, 'complete'])->middleware('throttle:office-mutation')->name('quotations.complete');
    Route::post('/quotations/{quotation}/submit', [QuotationController::class, 'submit'])->middleware('throttle:office-mutation')->name('quotations.submit');
    Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])->middleware('throttle:office-mutation')->name('quotations.approve');
    Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->middleware('throttle:office-mutation')->name('quotations.reject');
    Route::post('/quotations/{quotation}/void', [QuotationController::class, 'void'])->middleware('throttle:office-mutation')->name('quotations.void');
    Route::resource('quotations', QuotationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/logout', [SsoController::class, 'logout'])->middleware('throttle:office-mutation')->name('auth.logout');
});
