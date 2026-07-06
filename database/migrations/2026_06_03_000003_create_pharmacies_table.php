<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacist_id')->constrained('pharmacists')->onDelete('cascade');
            $table->string('pharmacy_name');
            $table->string('pharmacy_address');
            $table->string('certificate');
            $table->string('license');
            $table->enum('status', ['pending', 'approved','rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
