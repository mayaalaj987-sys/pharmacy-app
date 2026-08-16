<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('role_snapshot', 32)->nullable();
            $table->string('action', 96);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->enum('outcome', ['success', 'denied', 'failure']);
            $table->string('reason', 500)->nullable();
            $table->uuid('correlation_id');
            $table->text('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestampTz('logged_at');

            $table->index(['admin_id', 'logged_at']);
            $table->index(['action', 'logged_at']);
            $table->index('correlation_id');
        });

        $this->installAppendOnlyGuards();
    }

    public function down(): void
    {
        $this->dropAppendOnlyGuards();
        Schema::dropIfExists('admin_audit_logs');
    }

    private function installAppendOnlyGuards(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->installSqliteGuards(),
            'mysql', 'mariadb' => $this->installMysqlGuards(),
            'pgsql' => $this->installPostgresGuards(),
            default => null,
        };
    }

    private function dropAppendOnlyGuards(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->dropSqliteOrMysqlGuards(),
            'mysql', 'mariadb' => $this->dropSqliteOrMysqlGuards(),
            'pgsql' => DB::unprepared('DROP TRIGGER IF EXISTS admin_audit_logs_no_mutation ON admin_audit_logs; DROP FUNCTION IF EXISTS reject_admin_audit_mutation();'),
            default => null,
        };
    }

    private function dropSqliteOrMysqlGuards(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS admin_audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS admin_audit_logs_no_delete');
    }

    private function installSqliteGuards(): void
    {
        DB::unprepared("CREATE TRIGGER admin_audit_logs_no_update BEFORE UPDATE ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'admin audit logs are append-only'); END;");
        DB::unprepared("CREATE TRIGGER admin_audit_logs_no_delete BEFORE DELETE ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'admin audit logs are append-only'); END;");
    }

    private function installMysqlGuards(): void
    {
        DB::unprepared("CREATE TRIGGER admin_audit_logs_no_update BEFORE UPDATE ON admin_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'admin audit logs are append-only'");
        DB::unprepared("CREATE TRIGGER admin_audit_logs_no_delete BEFORE DELETE ON admin_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'admin audit logs are append-only'");
    }

    private function installPostgresGuards(): void
    {
        DB::unprepared("CREATE FUNCTION reject_admin_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'admin audit logs are append-only'; END; \$\$");
        DB::unprepared('CREATE TRIGGER admin_audit_logs_no_mutation BEFORE UPDATE OR DELETE ON admin_audit_logs FOR EACH ROW EXECUTE FUNCTION reject_admin_audit_mutation()');
    }
};
