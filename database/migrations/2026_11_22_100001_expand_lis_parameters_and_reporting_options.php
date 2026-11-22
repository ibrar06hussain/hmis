<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_catalog', function (Blueprint $table): void {
            $table->string('panel_code', 32)->nullable()->after('section')->index();
            $table->string('panel_name', 120)->nullable()->after('panel_code');
            $table->string('result_type', 24)->default('numeric')->after('specimen_type'); // numeric, select, positive_negative, text
            $table->json('reporting_options')->nullable()->after('result_type');
            $table->string('normal_text', 80)->nullable()->after('reporting_options');
            $table->string('method_name', 100)->nullable()->after('normal_text');
            $table->decimal('price_pkr', 10, 2)->default(0)->after('turnaround_minutes');
            $table->unsignedSmallInteger('display_order')->default(0)->after('price_pkr');
        });

        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->string('remarks', 500)->nullable()->after('amendment_reason');
        });
    }

    public function down(): void
    {
        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->dropColumn('remarks');
        });

        Schema::table('lab_test_catalog', function (Blueprint $table): void {
            $table->dropIndex(['panel_code']);
            $table->dropColumn([
                'panel_code',
                'panel_name',
                'result_type',
                'reporting_options',
                'normal_text',
                'method_name',
                'price_pkr',
                'display_order',
            ]);
        });
    }
};
