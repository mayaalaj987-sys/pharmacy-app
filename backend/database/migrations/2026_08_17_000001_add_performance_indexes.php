<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only indexes for the foreign keys and filter columns the
 * application reads on every request.
 *
 * SQLite (the local development driver) does not create an index for a
 * foreign key constraint, so every tenant-scoped query was a full table scan.
 * Composite indexes lead with the tenant column because `PharmacyContextResolver`
 * scopes every operational query by `pharmacy_id` first.
 *
 * No column, constraint or row is modified by this migration.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, string[]>>
     */
    private array $indexes = [
        'medicines' => [
            'medicines_pharmacy_expire_index' => ['pharmacy_id', 'expire_date'],
            'medicines_supplier_pharmacy_index' => ['supplier_id', 'pharmacy_id'],
        ],
        'sales' => [
            'sales_pharmacy_date_index' => ['pharmacy_id', 'date'],
            'sales_employee_index' => ['employee_id'],
            'sales_pharmacist_index' => ['pharmacist_id'],
        ],
        'sale_items' => [
            'sale_items_sale_index' => ['sale_id'],
            'sale_items_medicine_index' => ['medicine_id'],
        ],
        'orders' => [
            'orders_pharmacy_status_index' => ['pharmacy_id', 'status'],
            'orders_supplier_index' => ['supplier_id'],
        ],
        'order_items' => [
            'order_items_order_index' => ['order_id'],
            'order_items_medicine_index' => ['medicine_id'],
        ],
        'tasks' => [
            'tasks_pharmacy_status_index' => ['pharmacy_id', 'status'],
            'tasks_employee_status_index' => ['employee_id', 'status'],
        ],
        'notifications' => [
            'notifications_pharmacy_read_index' => ['pharmacy_id', 'is_read'],
            'notifications_pharmacy_created_index' => ['pharmacy_id', 'created_at'],
        ],
        'pharmacies' => [
            'pharmacies_pharmacist_index' => ['pharmacist_id'],
            'pharmacies_status_index' => ['status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $definitions) {
                foreach ($definitions as $name => $columns) {
                    if (Schema::hasIndex($table, $name)) {
                        continue;
                    }

                    $blueprint->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $definitions) {
                foreach (array_keys($definitions) as $name) {
                    if (! Schema::hasIndex($table, $name)) {
                        continue;
                    }

                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
