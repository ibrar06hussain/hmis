<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('encounter_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('attending_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('class', 24)->default('AMB'); // AMB (OPD), EMER, IMP (IPD), CARD_PROC
            $table->string('status', 24)->default('arrived'); // planned, arrived, in_progress, finished, cancelled
            $table->string('chief_complaint', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('patient_allergies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('substance', 150);
            $table->string('category', 32)->default('medication'); // medication, food, contrast_dye, environment, latex
            $table->string('severity', 24)->default('moderate'); // mild, moderate, severe, life_threatening
            $table->string('reaction', 255)->nullable();
            $table->string('clinical_status', 24)->default('active'); // active, inactive, resolved
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'clinical_status']);
        });

        // PACS-ready placeholder table (no pixel data stored in HMIS database)
        Schema::create('imaging_studies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('accession_number', 40)->unique();
            $table->string('modality', 16); // ECHO, ECG, CR, CT, MR, XA, US
            $table->string('procedure_name', 160);
            $table->string('study_instance_uid', 128)->nullable()->unique(); // DICOM 0020,000D
            $table->string('pacs_server_key', 48)->nullable();
            $table->string('status', 24)->default('ordered'); // ordered, scheduled, in_progress, reported, verified, cancelled
            $table->text('report_summary')->nullable();
            $table->timestamp('study_date')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'study_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_studies');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('encounters');
    }
};
