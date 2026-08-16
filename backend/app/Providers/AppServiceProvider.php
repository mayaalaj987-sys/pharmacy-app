<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Models\Task;
use App\Policies\AdminPolicy;
use App\Policies\EmployeeDocumentVersionPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\MedicinePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PharmacyDocumentVersionPolicy;
use App\Policies\PharmacyPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::policy(Pharmacy::class, PharmacyPolicy::class);
        Gate::policy(Medicine::class, MedicinePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(PharmacyDocumentVersion::class, PharmacyDocumentVersionPolicy::class);
        Gate::policy(EmployeeDocumentVersion::class, EmployeeDocumentVersionPolicy::class);

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Admin::normalizeEmail((string) $request->input('email', ''));

            return Limit::perMinute(5)->by(hash('sha256', $email.'|'.$request->ip()));
        });

        RateLimiter::for('account-security', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by(hash('sha256', $userId.'|'.$request->ip()));
        });
    }
}
