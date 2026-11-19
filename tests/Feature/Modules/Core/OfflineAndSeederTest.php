<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Core;

use App\Models\User;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_and_seeds_hospital_structure(): void
    {
        config()->set('hmis.demo_mode', true);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Facility::query()->count());
        $this->assertGreaterThanOrEqual(12, Department::query()->count());
        $this->assertSame(3, Patient::query()->count());
    }

    public function test_rendered_screens_contain_zero_external_cdn_dependencies(): void
    {
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);

        $loginHtml = $this->get('/login')->assertOk()->getContent();
        $this->assertNoExternalCdns((string) $loginHtml);

        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $dashboardHtml = $this->actingAs($consultant)->get('/dashboard')->assertOk()->getContent();
        $this->assertNoExternalCdns((string) $dashboardHtml);

        $patient = Patient::query()->firstOrFail();
        $profileHtml = $this->actingAs($consultant)->get(route('patients.show', $patient))->assertOk()->getContent();
        $this->assertNoExternalCdns((string) $profileHtml);

        $opdHtml = $this->actingAs($consultant)->get(route('opd.index'))->assertOk()->getContent();
        $this->assertNoExternalCdns((string) $opdHtml);

        $encounter = $patient->encounters()->firstOrFail();
        $encounterHtml = $this->actingAs($consultant)->get(route('encounters.show', $encounter))->assertOk()->getContent();
        $this->assertNoExternalCdns((string) $encounterHtml);
    }

    private function assertNoExternalCdns(string $html): void
    {
        foreach (['cdn.jsdelivr.net', 'fonts.googleapis.com', 'unpkg.com', 'cdnjs.cloudflare.com', 'stackpath.bootstrapcdn.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $html, "Unexpected external CDN reference detected: {$cdn}");
        }
    }
}
