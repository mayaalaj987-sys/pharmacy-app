<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets raised from the mobile app and answered by an administrator.
 *
 * The sender is recorded as a foreign key, never as a free-text name or email:
 * identity comes from the authenticated token, so a ticket cannot be attributed
 * to someone who did not send it. Both actor types can write, mirroring the
 * `sales` table's pharmacist/employee pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Exactly one of these is set, decided server-side from the token.
            $table->foreignId('pharmacist_id')->nullable()->constrained('pharmacists')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            // Context for the admin: which pharmacy the sender was working in.
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();

            $table->string('subject');
            $table->text('message');

            $table->enum('status', ['open', 'resolved'])->default('open');

            $table->text('admin_response')->nullable();
            // Restricted, not nulled: an answered ticket must keep naming its
            // author, the same rule the audit log follows.
            $table->foreignId('responded_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // The admin queue reads open tickets oldest-first; a sender reads
            // their own newest-first.
            $table->index(['status', 'created_at'], 'support_tickets_status_created_index');
            $table->index(['pharmacist_id', 'created_at'], 'support_tickets_pharmacist_index');
            $table->index(['employee_id', 'created_at'], 'support_tickets_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
