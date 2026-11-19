<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('facility_code', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['facility_code', 'year']);
        });

        Schema::create('emergency_triages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('encounter_id')->unique()->constrained('encounters')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->unsignedTinyInteger('triage_level'); // 1=Resuscitation, 2=Emergent, 3=Urgent, 4=Less Urgent, 5=Non-Urgent
            $table->string('arrival_mode', 32)->default('walk_in'); // walk_in, ambulance_1122, referral, police
            $table->boolean('chest_pain_flag')->default(false);
            $table->boolean('stemi_alert')->default(false);
            $table->string('presenting_complaint', 255);
            $table->text('triage_notes')->nullable();
            $table->string('disposition', 32)->default('pending'); // pending, admitted_ipd, admitted_ccu, cath_lab, discharged, referred, lama, expired
            $table->foreignId('triaged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at');
            $table->timestamps();

            $table->index(['disposition', 'triage_level', 'triaged_at']);
            $table->index(['patient_id', 'triaged_at']);
        });

        Schema::create('ipd_admissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('admission_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->unique()->constrained('encounters')->restrictOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->restrictOnDelete();
            $table->foreignId('admitting_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('attending_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();

            $table->string('admission_type', 32)->default('emergency'); // emergency, elective, transfer_in, cardiac_surgery
            $table->string('status', 24)->default('admitted')->index(); // admitted, discharged, transferred_out, lama, expired
            $table->string('admission_diagnosis', 255);
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->string('discharge_condition', 48)->nullable(); // stable, improved, unchanged, critical_referred, expired
            $table->text('follow_up_plan')->nullable();
            $table->foreignId('admitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discharged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['ward_id', 'status']);
        });

        Schema::create('bed_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('ipd_admissions')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();
            $table->foreignId('bed_id')->constrained('beds')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transfer_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['bed_id', 'released_at']);
            $table->index(['admission_id', 'allocated_at']);
        });

        Schema::create('terminology_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('coding_system', 24); // ICD-10, ICD-11, LOINC
            $table->string('terminology_version', 32);
            $table->string('code', 32)->index();
            $table->string('display_name', 255);
            $table->string('normalized_display', 255)->index();
            $table->string('chapter', 120)->nullable();
            $table->json('aliases')->nullable();
            $table->boolean('is_cardiology_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['coding_system', 'terminology_version', 'code'], 'term_sys_ver_code_unique');
            $table->index(['coding_system', 'is_active']);
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('terminology_code_id')->nullable()->constrained('terminology_codes')->nullOnDelete();

            // Version-locked clinical snapshot: never mutated if terminology catalogue updates later
            $table->string('coding_system', 24);
            $table->string('terminology_version', 32);
            $table->string('code', 32)->index();
            $table->string('display_name', 255);

            $table->string('diagnosis_rank', 24)->default('primary'); // primary, secondary
            $table->string('clinical_status', 24)->default('confirmed'); // provisional, confirmed, active, resolved, ruled_out
            $table->string('clinical_context', 255)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['encounter_id', 'diagnosis_rank']);
            $table->index(['patient_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('terminology_codes');
        Schema::dropIfExists('bed_allocations');
        Schema::dropIfExists('ipd_admissions');
        Schema::dropIfExists('emergency_triages');
        Schema::dropIfExists('admission_sequences');
    }
};
