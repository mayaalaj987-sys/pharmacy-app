<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PharmacistController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [PharmacistController::class, 'register']);
Route::post('/login', [PharmacistController::class, 'login']);
Route::post('/employee/register', [EmployeeController::class, 'register']);
Route::post('/employee/login', [EmployeeController::class, 'login']);

Route::middleware(['auth:pharmacist,employee'])->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::post('/logout', [SessionController::class, 'logout']);
});

// Operational endpoints shared by both actor types require an approved tenant.
Route::middleware(['auth:pharmacist,employee', 'active.pharmacy'])->group(function () {
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

Route::middleware(['auth:employee', 'active.pharmacy'])->group(function () {
    Route::get('/sale/my-sales', [SaleController::class, 'getEmployeeSales']);
    Route::get('/tasks', [TaskController::class, 'getMyTasks']);
    Route::post('/tasks/{id}/done', [TaskController::class, 'markAsDone']);
});

Route::middleware(['auth:pharmacist'])->group(function () {
    Route::get('/test-auth', fn () => response()->json(['status' => 'auth ok']));
    Route::delete('/delete-account', [PharmacistController::class, 'deleteAccount']);
    Route::get('/profile', [PharmacistController::class, 'getProfile']);
    Route::post('/profile/update', [PharmacistController::class, 'updateProfile']);
    Route::post('/pharmacy/add', [PharmacistController::class, 'addPharmacy']);
    Route::post('/pharmacy/{id}/update', [PharmacistController::class, 'updatePharmacy']);
});

Route::middleware(['auth:pharmacist', 'active.pharmacy'])->group(function () {
    Route::get('/employees/pending', [EmployeeController::class, 'getAllPendingEmployees']);
    Route::post('/employees/approve/{id}', [EmployeeController::class, 'approveEmployee']);
    Route::get('/employees/{pharmacy_id}', [EmployeeController::class, 'getEmployees']);
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

    Route::post('/rating', [RatingController::class, 'submitRating']);
    Route::post('/tasks', [TaskController::class, 'createTask']);
    Route::get('/tasks/pharmacy', [TaskController::class, 'getPharmacyTasks']);
    Route::delete('/tasks/{id}', [TaskController::class, 'deleteTask']);
});

// Temporary key-based containment. This is not the final admin identity architecture.
Route::prefix('admin')->middleware(['admin', 'throttle:10,1'])->group(function () {
    Route::get('/pharmacies', [AdminDashboardController::class, 'getAllPharmacies']);
    Route::get('/pharmacies/pending', [AdminDashboardController::class, 'getPendingPharmacies']);
    Route::post('/pharmacies/{id}/approve', [AdminDashboardController::class, 'approvePharmacy']);
    Route::post('/pharmacies/{id}/reject', [AdminDashboardController::class, 'rejectPharmacy']);
});
