<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_order_sets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 140);
            $table->string('category', 40)->default('cardiac_protocol'); // cardiac_protocol, personal_favorite
            $table->string('description', 255)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_shared')->default(true);
            $table->string('default_note_type', 40)->default('cardiology_eval');
            $table->string('default_nyha_class', 10)->nullable();
            $table->string('default_chest_pain_type', 40)->nullable();
            $table->text('default_exam_text')->nullable();
            $table->text('default_assessment_text')->nullable();
            $table->text('default_plan_text')->nullable();
            $table->string('default_follow_up', 255)->nullable();
            $table->json('diagnosis_codes')->nullable();
            $table->json('medications')->nullable();
            $table->json('lab_test_codes')->nullable();
            $table->json('imaging_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['owner_user_id', 'is_active']);
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->string('meal_timing', 30)->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropColumn('meal_timing');
        });

        Schema::dropIfExists('clinical_order_sets');
    }
};
