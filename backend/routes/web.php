<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminPharmacyDocumentController;
use App\Http\Controllers\AdminPharmacyReviewController;
use App\Http\Controllers\AdminSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/admin')->group(function (): void {
    Route::get('/csrf', [AdminSessionController::class, 'csrf'])->name('admin.csrf');
    Route::post('/login', [AdminSessionController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('admin.login');

    Route::middleware('auth:admin')->group(function (): void {
        Route::post('/logout', [AdminSessionController::class, 'logout'])->name('admin.logout');

        Route::middleware(['admin.active', 'admin.audit'])->group(function (): void {
            Route::get('/session', [AdminSessionController::class, 'current'])->name('admin.session.current');

            Route::get('/analytics/pharmacies', [AdminAnalyticsController::class, 'pharmacies'])->name('admin.analytics.pharmacies');
            Route::get('/analytics/job-market', [AdminAnalyticsController::class, 'jobMarket'])->name('admin.analytics.job-market');
            Route::get('/analytics/onboarding', [AdminAnalyticsController::class, 'onboarding'])->name('admin.analytics.onboarding');

            Route::get('/review/applications', [AdminPharmacyReviewController::class, 'index'])->name('admin.review.applications.index');
            Route::get('/review/applications/{pharmacy}', [AdminPharmacyReviewController::class, 'show'])->name('admin.review.applications.show');
            Route::post('/review/applications/{pharmacy}/approve', [AdminPharmacyReviewController::class, 'approve'])->name('admin.review.applications.approve');
            Route::post('/review/applications/{pharmacy}/reject', [AdminPharmacyReviewController::class, 'reject'])->name('admin.review.applications.reject');
            Route::get('/review/applications/{pharmacy}/documents/{document:public_id}/preview', [AdminPharmacyDocumentController::class, 'preview'])->withoutScopedBindings()->name('admin.review.documents.preview');
            Route::get('/review/applications/{pharmacy}/documents/{document:public_id}/download', [AdminPharmacyDocumentController::class, 'download'])->withoutScopedBindings()->name('admin.review.documents.download');

            Route::middleware('admin.super')->group(function (): void {
                Route::get('/admins', [AdminManagementController::class, 'index'])->name('admin.accounts.index');
                Route::post('/admins', [AdminManagementController::class, 'store'])->name('admin.accounts.store');
                Route::patch('/admins/{admin}/role', [AdminManagementController::class, 'changeRole'])->name('admin.accounts.role');
                Route::post('/admins/{admin}/disable', [AdminManagementController::class, 'disable'])->name('admin.accounts.disable');
                Route::post('/admins/{admin}/reactivate', [AdminManagementController::class, 'reactivate'])->name('admin.accounts.reactivate');
            });
        });
    });
});
