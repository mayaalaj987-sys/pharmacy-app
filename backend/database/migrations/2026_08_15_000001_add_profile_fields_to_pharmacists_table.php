<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacists', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pharmacists', function (Blueprint $table) {
            $table->dropColumn(['phone', 'deactivated_at']);
        });
    }
};
