<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardiac_procedures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('procedure_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->string('category', 40); // cath_lab_pci, coronary_angiography, cardiac_surgery, pacemaker_icd, electrophysiology
            $table->string('procedure_name', 180);
            $table->string('theatre_or_lab', 80); // Cath Lab 1, Cardiac OT 1, etc.
            $table->string('status', 24)->default('scheduled')->index(); // scheduled, in_progress, completed, cancelled
            $table->foreignId('lead_operator_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('anesthetist_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();

            $table->boolean('who_checklist_completed')->default(false);
            $table->string('access_site', 48)->nullable(); // radial_right, radial_left, femoral, sternotomy, thoracotomy
            $table->unsignedSmallInteger('contrast_volume_ml')->nullable();
            $table->decimal('fluoroscopy_minutes', 6, 2)->nullable();
            $table->json('stents_implanted')->nullable();
            $table->json('grafts_performed')->nullable();
            $table->text('operative_findings')->nullable();
            $table->text('complications')->nullable();
            $table->text('post_op_orders')->nullable();

            $table->timestamp('scheduled_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'scheduled_at']);
            $table->index(['category', 'status']);
        });

        Schema::create('icu_device_observations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->string('device_code', 48)->index();
            $table->string('observation_type', 48); // heart_rate, spo2, nibp_systolic, nibp_diastolic, art_map, resp_rate, ventilator_fio2, infusion_rate
            $table->decimal('value_numeric', 10, 2)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('data_quality', 32)->default('good'); // good, artifact_suspect, sensor_disconnected
            $table->boolean('is_abnormal')->default(false)->index();
            $table->string('verification_status', 32)->default('unverified_raw')->index(); // unverified_raw, clinician_verified, rejected_artifact
            $table->string('waveform_reference', 160)->nullable();
            $table->timestamp('observed_at');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'observed_at']);
            $table->index(['bed_id', 'verification_status']);
        });

        Schema::create('ai_interaction_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('encounter_id')->nullable()->index();
            $table->string('tool_name', 80);
            $table->string('model_provider', 48)->default('disabled');
            $table->string('model_version', 64)->nullable();
            $table->string('prompt_summary', 255)->nullable();
            $table->text('response_summary')->nullable();
            $table->string('status', 40)->index(); // blocked_disabled, blocked_unauthorized, blocked_prompt_injection, blocked_prohibited_action, draft_generated, human_approved, human_rejected
            $table->foreignId('human_reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('human_reviewed_at')->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('occurred_at');

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interaction_logs');
        Schema::dropIfExists('icu_device_observations');
        Schema::dropIfExists('cardiac_procedures');
    }
};
