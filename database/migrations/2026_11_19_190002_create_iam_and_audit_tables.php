<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('username', 64)->nullable()->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employee_code', 32)->unique();
            $table->string('designation', 100);
            $table->string('specialty', 100)->nullable();
            $table->string('license_number', 64)->nullable(); // PMDC, PNC, Pharmacy Council, etc.
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 64)->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });

        Schema::create('break_glass_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('reason_code', 64);
            $table->text('justification');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'patient_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('staff_profiles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'username', 'is_active', 'last_login_at', 'last_login_ip']);
        });
    }
};
