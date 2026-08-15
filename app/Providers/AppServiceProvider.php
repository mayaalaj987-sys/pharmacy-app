<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Task;
use App\Policies\EmployeePolicy;
use App\Policies\MedicinePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PharmacyPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Pharmacy::class, PharmacyPolicy::class);
        Gate::policy(Medicine::class, MedicinePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
    }
}
