<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PharmacistController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\RatingController;


Route::post('/register',          [PharmacistController::class, 'register']);
Route::post('/register/pharmacy', [PharmacistController::class, 'registerPharmacy']);
Route::post('/login',             [PharmacistController::class, 'login']);
Route::post('/employee/register', [EmployeeController::class,   'register']);   // ✅ UPDATED: بدون اسم صيدلية
Route::post('/employee/login',    [EmployeeController::class,   'login']);



Route::middleware(['auth:sanctum'])->get('/test', function () {
    return response()->json(['msg' => 'ok']);
});

Route::middleware(['auth:sanctum', 'role:employee'])->group(function () {
    Route::get('/medicines',                [MedicineController::class,     'getMedicines']);
    Route::get('/medicines/search',         [MedicineController::class,     'searchMedicine']);
    Route::get('/medicines/low-stock',      [MedicineController::class,     'getLowStockMedicines']);
    Route::get('/medicines/expiring',       [MedicineController::class,     'getExpiringMedicines']);
    Route::get('/medicines/out-of-stock',   [MedicineController::class,     'getOutOfStockMedicines']);
    Route::get('/medicines/category',       [MedicineController::class,     'getMedicinesByCategory']);
    Route::post('/sale/create',             [SaleController::class,         'createSale']);
    Route::get('/sale/my-sales',            [SaleController::class,         'getEmployeeSales']);
    Route::get('/notifications',            [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/tasks',                    [TaskController::class,         'getMyTasks']);
    Route::post('/tasks/{id}/done',         [TaskController::class,         'markAsDone']);
});

// ===== Admin Routes =====
Route::prefix('admin')->group(function () {
    Route::get('/pharmacies',               [PharmacistController::class, 'getAllPharmacies']);
    Route::get('/pharmacies/pending',       [PharmacistController::class, 'getPendingPharmacies']);
    Route::post('/pharmacies/{id}/approve', [PharmacistController::class, 'approvePharmacy']);
    Route::post('/pharmacies/{id}/reject',  [PharmacistController::class, 'rejectPharmacy']);
});

// ===== Pharmacist Routes =====
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout',                  [PharmacistController::class,   'logout']);
    Route::delete('/delete-account',        [PharmacistController::class,   'deleteAccount']);
    Route::get('/profile',                  [PharmacistController::class,   'getProfile']);
    Route::post('/profile/update',          [PharmacistController::class,   'updateProfile']);
    Route::post('/pharmacy/add',            [PharmacistController::class,   'addPharmacy']);// ✅ NEW: تعديل معلومات صيدلية معينة
    Route::post('/pharmacy/{id}/update',    [PharmacistController::class,   'updatePharmacy']);// ✅ UPDATED: كل طلبات التوظيف المفتوحة (بدون صيدلية محددة)
    Route::get('/employees/pending',        [EmployeeController::class,     'getAllPendingEmployees']);
    Route::post('/employees/approve/{id}',  [EmployeeController::class,     'approveEmployee']);
    Route::post('/employees/reject/{id}',   [EmployeeController::class,     'rejectEmployee']);
    Route::get('/employees/{pharmacy_id}',  [EmployeeController::class,     'getEmployees']);
    Route::delete('/employees/{id}/dismiss',[EmployeeController::class,     'dismissEmployee']);
    Route::post('/sale/create',             [SaleController::class,         'createSale']);
    Route::get('/sale/daily',               [SaleController::class,         'getDailySales']);
    Route::get('/sale/all',                 [SaleController::class,         'getAllSales']);
    Route::get('/medicines',                [MedicineController::class,     'getMedicines']);
    Route::get('/medicines/search',         [MedicineController::class,     'searchMedicine']);
    Route::post('/medicines/add',           [MedicineController::class,     'addMedicine']);
    Route::post('/medicines/edit/{id}',     [MedicineController::class,     'editMedicine']);
    Route::get('/medicines/expiring',       [MedicineController::class,     'getExpiringMedicines']);
    Route::get('/medicines/low-stock',      [MedicineController::class,     'getLowStockMedicines']);
    Route::get('/medicines/out-of-stock',   [MedicineController::class,     'getOutOfStockMedicines']);
    Route::get('/medicines/category',       [MedicineController::class,     'getMedicinesByCategory']);
    Route::get('/suppliers',                [SupplierController::class,     'getSuppliers']);
    Route::get('/suppliers/{id}/medicines', [SupplierController::class,     'getSupplierMedicines']);
    Route::post('/orders',                  [OrderController::class,        'createOrder']);
    Route::post('/orders/{id}/receive',     [OrderController::class,        'receiveOrder']);
    Route::post('/orders/{id}/cancel',      [OrderController::class,        'cancelOrder']);
    Route::get('/orders',                   [OrderController::class,        'getOrders']);
    Route::get('/orders/{id}',              [OrderController::class,        'getOrder']);
    Route::get('/reports/dashboard',        [ReportController::class,       'getDashboard']);
    Route::get('/reports/revenue',          [ReportController::class,       'getRevenue']);
    Route::get('/reports/inventory-value',  [ReportController::class,       'getInventoryValue']);
    Route::get('/reports/average-sales',    [ReportController::class,       'getAverageSales']);
    Route::get('/reports/profits',          [ReportController::class,       'getProfits']);
    Route::get('/reports/most-sold',        [ReportController::class,       'getMostSoldMedicines']);
    Route::get('/reports/most-sold-category',[ReportController::class,      'getMostSoldByCategory']);
    Route::get('/notifications',            [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',  [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}',    [NotificationController::class, 'deleteNotification']);
    Route::post('/rating',                  [RatingController::class,       'submitRating']);
    Route::post('/tasks',                   [TaskController::class,         'createTask']);
    Route::get('/tasks/pharmacy',           [TaskController::class,         'getPharmacyTasks']);
    Route::delete('/tasks/{id}',            [TaskController::class,         'deleteTask']);
});

