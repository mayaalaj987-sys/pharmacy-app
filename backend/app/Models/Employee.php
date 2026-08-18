<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * In the hiring pool, looking for work.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Employed at a pharmacy.
     */
    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLE_TRAINEE = 'trainee';

    protected $fillable = [
        'pharmacy_id', 'name', 'phone', 'email', 'password',
        'cv', 'experience_proof', 'salary', 'role', 'status', 'first_login',
    ];

    // `cv` and `experience_proof` look vestigial — registration writes '' and
    // null into them and the real files live in employee_document_versions —
    // but DocumentAvailabilityService still reads both to report what a legacy
    // account has on file, and documents:migrate-legacy reads them to find the
    // originals. They stay until that migration is retired everywhere.
    protected $hidden = [
        'password',
        'cv',
        'experience_proof',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'first_login' => 'boolean',
        ];
    }

    /**
     * Whether this person currently works somewhere.
     *
     * `pharmacy_id` is the source of truth, not `status`: the column is
     * `onDelete('set null')`, so a deleted pharmacy leaves an employee
     * detached while `status` still reads 'approved'.
     */
    public function isEmployed(): bool
    {
        return $this->pharmacy_id !== null;
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function documentVersions()
    {
        return $this->hasMany(EmployeeDocumentVersion::class);
    }
}
