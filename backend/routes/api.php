<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EmployeeAccountController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeNotificationController;
use App\Http\Controllers\EmployeeOfferController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PharmacistController;
use App\Http\Controllers\PharmacyDocumentController;
use App\Http\Controllers\PharmacyProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// The only unauthenticated writes in the API. Both pairs are throttled: the
// admin console has had brute-force protection since it was built and these
// never got it, even though they cover every account on the platform.
Route::post('/register', [PharmacistController::class, 'register'])
    ->middleware('throttle:registration');
Route::post('/login', [PharmacistController::class, 'login'])
    ->middleware('throttle:mobile-login');
Route::post('/employee/register', [EmployeeController::class, 'register'])
    ->middleware('throttle:registration');
Route::post('/employee/login', [EmployeeController::class, 'login'])
    ->middleware('throttle:mobile-login');

Route::middleware(['auth:pharmacist,employee'])->group(function () {
    Route::post('/logout', [SessionController::class, 'logout']);
});

Route::get('/registration/status', [PharmacistController::class, 'registrationStatus'])
    ->middleware(['auth:pharmacist', 'abilities:registration-status']);

Route::middleware(['auth:pharmacist,employee', 'abilities:app', 'active.account', 'approved.pharmacist'])->group(function () {
    Route::get('/me', [SessionController::class, 'me']);

    // Support deliberately sits outside the active-pharmacy group: someone
    // whose pharmacy context is broken is exactly who needs to reach support.
    Route::get('/support/tickets', [SupportTicketController::class, 'index']);
    Route::post('/support/tickets', [SupportTicketController::class, 'store'])
        ->middleware('throttle:account-security');
});

// Operational endpoints shared by both actor types require an approved tenant.
Route::middleware(['auth:pharmacist,employee', 'abilities:app', 'active.account', 'approved.pharmacist', 'active.pharmacy'])->group(function () {
    Route::get('/medicines', [MedicineController::class, 'getMedicines']);
    Route::get('/medicines/search', [MedicineController::class, 'searchMedicine']);
    Route::get('/medicines/low-stock', [MedicineController::class, 'getLowStockMedicines']);
    Route::get('/medicines/expiring', [MedicineController::class, 'getExpiringMedicines']);
    Route::get('/medicines/out-of-stock', [MedicineController::class, 'getOutOfStockMedicines']);
    Route::get('/medicines/category', [MedicineController::class, 'getMedicinesByCategory']);
    Route::post('/sale/create', [SaleController::class, 'createSale']);
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
});

Route::middleware(['auth:employee', 'abilities:app', 'active.pharmacy'])->group(function () {
    Route::get('/sale/my-sales', [SaleController::class, 'getEmployeeSales']);
    Route::get('/tasks', [TaskController::class, 'getMyTasks']);
    Route::post('/tasks/{id}/done', [TaskController::class, 'markAsDone']);
});

// Employee self-service. These document routes stay employee-only: a
// pharmacist presenting a valid token still gets 401 here, and reads an
// applicant's file through /recruitment/applicants/... instead, where the act
// is authorized by EmployeeDocumentVersionPolicy::viewAsRecruiter, logged, and
// reported to the applicant. The legacy Admin key still has no route at all.
Route::middleware(['auth:employee', 'abilities:app'])->group(function () {
    // Employee self-service account management. Privileged fields are rejected
    // by the Form Requests; the actor is always the authenticated employee.
    Route::get('/employee/profile', [EmployeeAccountController::class, 'show']);
    Route::post('/employee/profile/update', [EmployeeAccountController::class, 'update']);
    Route::post('/employee/password/change', [EmployeeAccountController::class, 'changePassword'])
        ->middleware('throttle:account-security');

    // The employee's own bell. The pharmacy-scoped notification routes sit
    // behind active.pharmacy, which someone waiting on a job does not have.
    // Readable without a pharmacy, which is the whole point.
    Route::get('/employee/offers', [EmployeeOfferController::class, 'index']);

    Route::get('/employee/notifications', [EmployeeNotificationController::class, 'index']);
    Route::post('/employee/notifications/{id}/read', [EmployeeNotificationController::class, 'markAsRead'])
        ->whereNumber('id');

    Route::get('/employee/documents', [EmployeeDocumentController::class, 'index']);
    Route::post('/employee/documents/{type}', [EmployeeDocumentController::class, 'store']);
    Route::get('/employee/documents/{document}/download', [EmployeeDocumentController::class, 'download'])
        ->name('employee-documents.download');
});

Route::middleware(['auth:pharmacist', 'abilities:app', 'active.account', 'approved.pharmacist'])->group(function () {
    Route::get('/test-auth', fn () => response()->json(['status' => 'auth ok']));
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/password/change', [AccountController::class, 'changePassword'])
        ->middleware('throttle:account-security');
    Route::post('/account/deactivate', [AccountController::class, 'deactivate'])
        ->middleware('throttle:account-security');
    Route::post('/pharmacy/add', [PharmacistController::class, 'addPharmacy']);
});

Route::middleware(['auth:pharmacist', 'abilities:app', 'active.account', 'approved.pharmacist', 'active.pharmacy'])->group(function () {
    Route::post('/pharmacy/profile/update', [PharmacyProfileController::class, 'update']);
    Route::get('/pharmacy/documents', [PharmacyDocumentController::class, 'index']);
    Route::post('/pharmacy/documents/{type}', [PharmacyDocumentController::class, 'store']);
    Route::get('/pharmacy/documents/{document}/download', [PharmacyDocumentController::class, 'download'])
        ->name('pharmacy-documents.download');
    Route::get('/employees/pending', [RecruitmentController::class, 'pool']);
    Route::get('/recruitment/offers', [RecruitmentController::class, 'offers']);
    Route::post('/recruitment/offers', [RecruitmentController::class, 'sendOffer']);
    Route::delete('/recruitment/offers/{offer}', [RecruitmentController::class, 'withdrawOffer'])
        ->whereNumber('offer');

    // The recruitment authorization model the comment below the employee group
    // was waiting for: a pharmacist may read the current documents of anyone in
    // the hiring pool, and of their own staff. Every open is logged and the
    // applicant is told. The /recruitment prefix keeps these clear of the
    // /employees/{pharmacy_id} wildcard.
    Route::get('/recruitment/applicants/{employee}/documents',
        [RecruitmentController::class, 'applicantDocuments'])->whereNumber('employee');
    Route::get('/recruitment/applicants/{employee}/documents/{document:public_id}/preview',
        [RecruitmentController::class, 'previewDocument'])
        ->whereNumber('employee')
        ->withoutScopedBindings()
        ->name('recruitment-documents.preview');
    Route::get('/recruitment/applicants/{employee}/documents/{document:public_id}/download',
        [RecruitmentController::class, 'downloadDocument'])
        ->whereNumber('employee')
        ->withoutScopedBindings()
        ->name('recruitment-documents.download');
    Route::post('/employees/approve/{id}', [EmployeeController::class, 'approveEmployee']);
    // Numeric only: this wildcard sits directly below literal siblings such as
    // /employees/pending and would otherwise swallow any word placed after it.
    Route::get('/employees/{pharmacy_id}', [EmployeeController::class, 'getEmployees'])
        ->whereNumber('pharmacy_id');
    Route::delete('/employees/{id}/dismiss', [EmployeeController::class, 'dismissEmployee']);

    Route::get('/sale/daily', [SaleController::class, 'getDailySales']);
    Route::get('/sale/all', [SaleController::class, 'getAllSales']);

    Route::post('/medicines/add', [MedicineController::class, 'addMedicine']);
    Route::post('/medicines/edit/{id}', [MedicineController::class, 'editMedicine']);

    Route::get('/suppliers', [SupplierController::class, 'getSuppliers']);
    Route::get('/suppliers/{id}/medicines', [SupplierController::class, 'getSupplierMedicines']);

    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::post('/orders/{id}/receive', [OrderController::class, 'receiveOrder']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'getOrder']);

    Route::get('/reports/dashboard', [ReportController::class, 'getDashboard']);
    Route::get('/reports/revenue', [ReportController::class, 'getRevenue']);
    Route::get('/reports/inventory-value', [ReportController::class, 'getInventoryValue']);
    Route::get('/reports/average-sales', [ReportController::class, 'getAverageSales']);
    Route::get('/reports/profits', [ReportController::class, 'getProfits']);
    Route::get('/reports/most-sold', [ReportController::class, 'getMostSoldMedicines']);
    Route::get('/reports/most-sold-category', [ReportController::class, 'getMostSoldByCategory']);

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);

    Route::get('/rating', [RatingController::class, 'myRating']);
    Route::post('/rating', [RatingController::class, 'submitRating']);
    Route::post('/tasks', [TaskController::class, 'createTask']);
    Route::get('/tasks/pharmacy', [TaskController::class, 'getPharmacyTasks']);
    Route::delete('/tasks/{id}', [TaskController::class, 'deleteTask']);
});
