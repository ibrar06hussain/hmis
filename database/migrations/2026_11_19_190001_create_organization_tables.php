<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 16)->unique();
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->string('facility_type', 40)->default('specialty_hospital');
            $table->string('city', 80)->default('Gilgit');
            $table->string('region', 100)->default('Gilgit-Baltistan');
            $table->string('country', 80)->default('Pakistan');
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->string('name', 120);
            $table->string('category', 40)->default('clinical'); // clinical, diagnostic, support, admin
            $table->boolean('is_clinical')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'is_active']);
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->string('name', 120);
            $table->string('ward_type', 40)->default('general'); // general, ccu, icu, surgical, emergency, private
            $table->string('floor', 30)->nullable();
            $table->unsignedInteger('bed_capacity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();
            $table->string('bed_number', 32);
            $table->string('status', 32)->default('available'); // available, occupied, cleaning, maintenance, reserved
            $table->boolean('has_cardiac_monitor')->default(false);
            $table->boolean('has_ventilator')->default(false);
            $table->boolean('is_isolation')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ward_id', 'bed_number']);
            $table->index(['ward_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facilities');
    }
};
