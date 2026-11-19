<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_mar_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('ipd_admission_id')->nullable()->constrained('ipd_admissions')->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->string('medication_name', 160);
            $table->string('dose_given', 64);
            $table->string('route', 24)->default('PO');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('administered_at');
            $table->string('status', 24)->default('administered'); // administered, held, refused, missed
            $table->string('clinical_reason', 255)->nullable();
            $table->foreignId('administered_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'administered_at']);
            $table->index(['encounter_id', 'administered_at']);
        });

        Schema::create('nursing_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('ipd_admission_id')->nullable()->constrained('ipd_admissions')->nullOnDelete();
            $table->string('shift', 24)->default('morning'); // morning, evening, night
            $table->string('note_category', 32)->default('assessment'); // assessment, shift_handover, fluid_balance, cardiac_monitoring
            $table->unsignedInteger('intake_ml')->nullable();
            $table->unsignedInteger('output_ml')->nullable();
            $table->text('content');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
        });

        Schema::create('duty_rosters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->date('duty_date');
            $table->string('shift', 24); // morning, evening, night, on_call
            $table->string('status', 24)->default('scheduled'); // scheduled, checked_in, completed, absent, swapped
            $table->string('notes', 255)->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_profile_id', 'duty_date', 'shift'], 'roster_staff_date_shift_unique');
            $table->index(['duty_date', 'department_id']);
        });

        Schema::create('pacs_viewer_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('study_instance_uid', 128);
            $table->string('launch_token_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('launched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['imaging_study_id', 'launched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacs_viewer_sessions');
        Schema::dropIfExists('duty_rosters');
        Schema::dropIfExists('nursing_notes');
        Schema::dropIfExists('nursing_mar_records');
    }
};
