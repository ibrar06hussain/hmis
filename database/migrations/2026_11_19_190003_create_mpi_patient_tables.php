<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrn_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('facility_code', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['facility_code', 'year']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mrn', 32)->unique();
            $table->string('status', 24)->default('active')->index(); // active, merged, deceased, inactive
            $table->foreignId('merged_into_patient_id')->nullable()->constrained('patients')->restrictOnDelete();
            $table->foreignId('registered_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('full_name', 160);
            $table->string('normalized_name', 160)->index();
            $table->string('father_or_spouse_name', 160)->nullable();
            $table->string('normalized_father_name', 160)->nullable()->index();

            $table->date('date_of_birth')->nullable()->index();
            $table->boolean('dob_estimated')->default(false);
            $table->string('sex', 16)->default('unknown'); // male, female, other, unknown
            $table->string('blood_group', 8)->nullable(); // A+, A-, B+, B-, AB+, AB-, O+, O-, unknown
            $table->string('marital_status', 24)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('nationality', 64)->default('Pakistani');

            // Never primary key; nullable for emergencies, newborns, foreigners
            $table->string('cnic_normalized', 13)->nullable()->index();
            $table->string('phone_primary', 32)->nullable()->index();
            $table->string('phone_secondary', 32)->nullable();
            $table->string('email', 120)->nullable();

            $table->string('address_line', 255)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('tehsil', 80)->nullable();
            $table->string('district', 80)->nullable()->index();
            $table->string('province', 80)->default('Gilgit-Baltistan');

            $table->string('registration_type', 24)->default('standard'); // standard, emergency, newborn
            $table->boolean('is_unknown_emergency')->default(false);
            $table->string('identity_verification_status', 24)->default('verified'); // verified, unverified, pending_cnic
            $table->string('photo_path', 255)->nullable();
            $table->text('registration_notes')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'normalized_name']);
            $table->index(['status', 'cnic_normalized']);
            $table->index(['status', 'phone_primary']);
        });

        Schema::create('patient_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('system', 48); // cnic, passport, sehat_card, external_mrn, merged_mrn, legacy_mrn
            $table->string('value', 100);
            $table->string('issuer', 120)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('verified')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->index(['system', 'value']);
            $table->index(['patient_id', 'system']);
        });

        Schema::create('patient_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('contact_type', 32)->default('emergency'); // emergency, guardian, next_of_kin
            $table->string('full_name', 160);
            $table->string('relationship', 64);
            $table->string('phone', 32)->nullable();
            $table->string('cnic_normalized', 13)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['patient_id', 'contact_type']);
        });

        Schema::create('patient_merges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('target_patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('merged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('snapshot_metadata')->nullable();
            $table->timestamp('merged_at');
            $table->timestamps();

            $table->unique('source_patient_id');
            $table->index('target_patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_merges');
        Schema::dropIfExists('patient_contacts');
        Schema::dropIfExists('patient_identifiers');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('mrn_sequences');
    }
};
