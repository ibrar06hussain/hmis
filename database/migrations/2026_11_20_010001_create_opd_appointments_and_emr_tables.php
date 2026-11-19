<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('facility_code', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['facility_code', 'year']);
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('clinician_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->unique()->constrained('encounters')->nullOnDelete();
            $table->date('appointment_date');
            $table->unsignedInteger('token_number');
            $table->string('visit_type', 24)->default('new'); // new, follow_up, urgent
            $table->string('status', 24)->default('booked'); // booked, checked_in, in_consult, completed, no_show, cancelled
            $table->string('chief_complaint', 255)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['department_id', 'appointment_date', 'token_number'], 'appt_dept_date_token_unique');
            $table->index(['appointment_date', 'department_id', 'status']);
            $table->index(['patient_id', 'appointment_date']);
        });

        Schema::create('vital_signs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');

            $table->unsignedSmallInteger('systolic_bp')->nullable();      // mmHg
            $table->unsignedSmallInteger('diastolic_bp')->nullable();     // mmHg
            $table->unsignedSmallInteger('heart_rate')->nullable();       // bpm
            $table->unsignedSmallInteger('respiratory_rate')->nullable(); // breaths/min
            $table->decimal('temperature_c', 4, 1)->nullable();           // Celsius
            $table->unsignedTinyInteger('spo2_percent')->nullable();      // %
            $table->decimal('weight_kg', 5, 2)->nullable();               // kg
            $table->decimal('height_cm', 5, 1)->nullable();               // cm
            $table->decimal('bmi', 4, 1)->nullable();                     // kg/m^2
            $table->unsignedSmallInteger('blood_glucose_mg_dl')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();        // 0-10
            $table->string('rhythm_note', 100)->nullable();               // e.g., Regular, Irregularly irregular
            $table->boolean('is_abnormal')->default(false);
            $table->json('abnormal_flags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['encounter_id', 'recorded_at']);
        });

        Schema::create('clinical_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('author_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();

            $table->string('note_type', 32)->default('opd_consult'); // opd_consult, cardiology_eval, er_triage, follow_up, nursing_note
            $table->string('status', 24)->default('draft');          // draft, signed, amended
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('supersedes_note_id')->nullable()->constrained('clinical_notes')->restrictOnDelete();
            $table->text('amendment_reason')->nullable();

            // Structured EMR fields
            $table->text('chief_complaint')->nullable();
            $table->text('history_of_present_illness')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('medication_history')->nullable();
            $table->text('physical_examination')->nullable();
            $table->text('assessment_and_diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->text('referral_notes')->nullable();
            $table->json('structured_payload')->nullable(); // Cardiology template fields (NYHA, chest pain type, risk factors)

            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['encounter_id', 'status']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('encounter_sequences');
    }
};
