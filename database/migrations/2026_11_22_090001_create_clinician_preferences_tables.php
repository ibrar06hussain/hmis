<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_quick_chips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32); // symptom, exam, ecg, diagnosis_shortcut
            $table->string('label', 160);
            $table->string('value', 255);
            $table->foreignId('terminology_code_id')->nullable()->constrained('terminology_codes')->nullOnDelete();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'category', 'label'], 'cqc_user_cat_label_unique');
            $table->index(['user_id', 'category', 'is_hidden']);
        });

        Schema::create('clinician_favorite_medications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->cascadeOnDelete();
            $table->string('default_dose', 60)->default('1 tab');
            $table->string('default_route', 16)->default('PO');
            $table->string('default_frequency', 16)->default('OD');
            $table->unsignedSmallInteger('default_duration_days')->default(14);
            $table->string('default_meal_timing', 30)->default('after_meal');
            $table->string('default_instructions', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'medication_id'], 'cfm_user_med_unique');
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->foreignId('added_by_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->boolean('is_hospital_formulary')->default(true)->after('added_by_user_id');
            $table->index(['is_active', 'is_hospital_formulary', 'added_by_user_id'], 'meds_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->dropIndex('meds_visibility_idx');
            $table->dropConstrainedForeignId('added_by_user_id');
            $table->dropColumn('is_hospital_formulary');
        });

        Schema::dropIfExists('clinician_favorite_medications');
        Schema::dropIfExists('clinician_quick_chips');
    }
};
