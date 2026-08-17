<?php

use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminInboxController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminPharmacyController;
use App\Http\Controllers\AdminPharmacyDocumentController;
use App\Http\Controllers\AdminPharmacyReviewController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AdminSupportTicketController;
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

            Route::get('/announcements/audience', [AdminNotificationController::class, 'audience'])->name('admin.announcements.audience');
            Route::post('/announcements', [AdminNotificationController::class, 'store'])->name('admin.announcements.store');

            Route::get('/support/tickets', [AdminSupportTicketController::class, 'index'])->name('admin.support.tickets.index');
            Route::post('/support/tickets/{ticket}/respond', [AdminSupportTicketController::class, 'respond'])->name('admin.support.tickets.respond');

            Route::get('/inbox', [AdminInboxController::class, 'index'])->name('admin.inbox.index');

            Route::get('/pharmacies', [AdminPharmacyController::class, 'index'])->name('admin.pharmacies.index');
            Route::post('/pharmacies/{pharmacy}/block', [AdminPharmacyController::class, 'block'])->name('admin.pharmacies.block');
            Route::post('/pharmacies/{pharmacy}/unblock', [AdminPharmacyController::class, 'unblock'])->name('admin.pharmacies.unblock');

            Route::get('/activity', [AdminActivityController::class, 'index'])->name('admin.activity.index');

            Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview'])->name('admin.analytics.overview');
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
