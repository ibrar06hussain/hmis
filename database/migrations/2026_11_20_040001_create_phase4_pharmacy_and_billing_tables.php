<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('generic_name', 150)->index();
            $table->string('brand_name', 150)->nullable();
            $table->string('drug_class', 100)->nullable();
            $table->string('dosage_form', 48)->default('tablet'); // tablet, capsule, injection, iv_infusion, sublingual, syrup
            $table->string('strength', 64);
            $table->decimal('unit_price_pkr', 10, 2)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(50);
            $table->boolean('is_high_alert')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('prescription_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignId('prescribed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('active')->index(); // active, partially_dispensed, dispensed, cancelled
            $table->boolean('allergy_override_used')->default(false);
            $table->string('allergy_override_reason', 255)->nullable();
            $table->text('clinical_notes')->nullable();
            $table->timestamp('prescribed_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'prescribed_at']);
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prescription_id')->constrained('prescriptions')->restrictOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->restrictOnDelete();
            $table->string('dose', 64);
            $table->string('route', 24)->default('PO'); // PO, IV, SL, SC, IM
            $table->string('frequency', 32)->default('OD'); // OD, BD, TDS, QID, STAT, PRN
            $table->unsignedSmallInteger('duration_days')->default(7);
            $table->unsignedInteger('quantity_prescribed');
            $table->unsignedInteger('quantity_dispensed')->default(0);
            $table->string('instructions', 255)->nullable();
            $table->string('status', 24)->default('pending')->index(); // pending, dispensed, cancelled
            $table->foreignId('dispensed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('medication_id')->constrained('medications')->restrictOnDelete();
            $table->string('movement_type', 32); // initial_stock, dispense, receipt, adjustment
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
        });

        Schema::create('service_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('category', 48); // consultation, lab, imaging, pharmacy, bed_day, procedure
            $table->decimal('unit_price_pkr', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('billing_category', 32)->default('general'); // general, sehat_card, zakat, govt_employee, indigent_waiver
            $table->string('status', 24)->default('issued')->index(); // issued, partially_paid, paid, voided
            $table->decimal('subtotal_pkr', 12, 2)->default(0);
            $table->decimal('exemption_pkr', 12, 2)->default(0);
            $table->decimal('net_payable_pkr', 12, 2)->default(0);
            $table->decimal('paid_pkr', 12, 2)->default(0);
            $table->decimal('balance_pkr', 12, 2)->default(0);
            $table->string('exemption_reason', 255)->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['patient_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('service_tariff_id')->nullable()->constrained('service_tariffs')->nullOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price_pkr', 10, 2);
            $table->decimal('line_total_pkr', 12, 2);
            $table->timestamps();
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entry_number', 32)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('entry_type', 32); // charge, exemption, payment, refund, void_reversal
            $table->string('payment_method', 32)->nullable(); // cash, card, bank_transfer, sehat_card, govt_waiver
            $table->decimal('amount_pkr', 12, 2);
            $table->string('reference_note', 255)->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');

            $table->index(['invoice_id', 'recorded_at']);
            $table->index(['patient_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_tariffs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medications');
    }
};
