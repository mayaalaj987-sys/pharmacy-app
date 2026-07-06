<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->onDelete('set null');
            $table->string('name');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('cv');
            $table->string('experience_proof')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->enum('role', ['employee', 'trainee']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('first_login')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
