<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('facility_code', 16);
            $table->string('prefix', 16); // LAB, SMP, ACC
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['facility_code', 'prefix', 'year'], 'diag_seq_unique');
        });

        Schema::create('lab_test_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('loinc_code', 32)->nullable()->index();
            $table->string('section', 48)->default('biochemistry'); // cardiac_markers, biochemistry, hematology, coagulation
            $table->string('specimen_type', 48)->default('serum');
            $table->string('unit', 32)->nullable(); // UCUM unit
            $table->decimal('ref_low_male', 10, 3)->nullable();
            $table->decimal('ref_high_male', 10, 3)->nullable();
            $table->decimal('ref_low_female', 10, 3)->nullable();
            $table->decimal('ref_high_female', 10, 3)->nullable();
            $table->decimal('critical_low', 10, 3)->nullable();
            $table->decimal('critical_high', 10, 3)->nullable();
            $table->unsignedSmallInteger('turnaround_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 24)->default('routine'); // routine, urgent, stat
            $table->string('status', 24)->default('ordered')->index(); // ordered, collected, received, in_analysis, preliminary, verified, cancelled
            $table->text('clinical_notes')->nullable();
            $table->timestamp('ordered_at');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'ordered_at']);
            $table->index(['status', 'priority']);
        });

        Schema::create('specimens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('barcode', 40)->unique();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('specimen_type', 48);
            $table->string('status', 32)->default('pending_collection')->index(); // pending_collection, collected, received_in_lab, rejected, in_analysis, completed
            $table->foreignId('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['lab_order_id', 'status']);
        });

        Schema::create('lab_order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->restrictOnDelete();
            $table->foreignId('lab_test_catalog_id')->constrained('lab_test_catalog')->restrictOnDelete();
            $table->foreignId('specimen_id')->nullable()->constrained('specimens')->nullOnDelete();

            $table->string('status', 32)->default('ordered')->index(); // ordered, result_entered, tech_validated, verified, amended
            $table->decimal('result_value_numeric', 12, 3)->nullable();
            $table->string('result_value_text', 255)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('ref_low', 10, 3)->nullable();
            $table->decimal('ref_high', 10, 3)->nullable();
            $table->string('abnormal_flag', 24)->nullable(); // normal, low, high, critical_low, critical_high
            $table->boolean('is_critical')->default(false)->index();
            $table->foreignId('critical_acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('critical_acknowledged_at')->nullable();

            $table->string('result_source', 24)->default('manual'); // manual, analyzer
            $table->string('analyzer_device_code', 48)->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->foreignId('tech_validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tech_validated_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('amendment_reason', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['lab_order_id', 'status']);
        });

        Schema::create('analyzer_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_code', 48)->unique();
            $table->string('manufacturer', 100);
            $table->string('model', 100);
            $table->string('serial_number', 100)->nullable();
            $table->string('firmware_version', 64)->nullable();
            $table->string('protocol', 32)->default('hl7_v2'); // hl7_v2, astm_e1394, tcp_ip, rs232, file_drop, vendor_api
            $table->string('host_or_port', 120)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('interface_version', 32)->default('1.0');
            $table->string('connection_status', 24)->default('offline'); // online, offline, error
            $table->json('test_code_map')->nullable(); // {"TROP":"TROP-I", ...}
            $table->timestamp('last_communication_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('analyzer_raw_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('analyzer_device_id')->constrained('analyzer_devices')->restrictOnDelete();
            $table->string('message_hash', 64)->unique(); // SHA-256 duplicate message prevention
            $table->string('control_id', 64)->nullable()->index();
            $table->string('protocol', 32);
            $table->text('raw_payload');
            $table->string('parse_status', 24)->default('received'); // received, staged, duplicate, failed
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('imaging_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('modality', 16); // ECHO, ECG, CR, CT, MR, XA, US
            $table->boolean('requires_contrast')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Extend existing PACS-ready imaging_studies table non-destructively for full RIS ordering & reporting
        Schema::table('imaging_studies', function (Blueprint $table): void {
            $table->foreignId('imaging_catalog_id')->nullable()->after('encounter_id')->constrained('imaging_catalog')->nullOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->after('imaging_catalog_id')->constrained('users')->nullOnDelete();
            $table->string('priority', 24)->default('routine')->after('modality');
            $table->string('clinical_indication', 255)->nullable()->after('procedure_name');
            $table->foreignId('reported_by_user_id')->nullable()->after('report_summary')->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable()->after('reported_by_user_id');
            $table->foreignId('verified_by_user_id')->nullable()->after('reported_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('imaging_catalog_id');
            $table->dropConstrainedForeignId('ordered_by_user_id');
            $table->dropConstrainedForeignId('reported_by_user_id');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['priority', 'clinical_indication', 'reported_at', 'verified_at']);
        });

        Schema::dropIfExists('imaging_catalog');
        Schema::dropIfExists('analyzer_raw_messages');
        Schema::dropIfExists('analyzer_devices');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('specimens');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_test_catalog');
        Schema::dropIfExists('diagnostic_sequences');
    }
};
