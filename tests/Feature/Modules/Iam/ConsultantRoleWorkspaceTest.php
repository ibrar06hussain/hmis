<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Iam;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantRoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_consultant_sees_only_consultant_related_pages_in_navigation_and_dedicated_dashboard(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();

        $dashboard = $this->actingAs($consultant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Consultant Clinic Workspace')
            ->assertSee('OPD &amp; Appointments', false)
            ->assertSee('Co-Sign Tray')
            ->assertSee('My Order Sets')
            ->assertSee('Master Patient Index')
            ->assertSee('Emergency &amp; STEMI Triage', false)
            ->assertSee('Wards, Beds &amp; IPD', false)
            ->assertSee('Cath Lab &amp; Cardiac OT', false)
            ->assertSee('2FA Security');

        // Non-consultant workbenches & admin pages MUST NOT appear in the Consultant's navigation
        foreach ([
            'Pre-Consult Vitals',
            'Nursing &amp; MAR',
            'ICU Bedside Telemetry',
            'Laboratory (LIS)',
            'Radiology &amp; PACS (RIS)',
            'Hospital Pharmacy',
            'Billing &amp; Ledger',
            'Duty Rosters',
            'Executive Reports',
            'AI Governance',
            'Audit &amp; Break-Glass',
            'Register Patient',
            'Merge Patients',
        ] as $hiddenItem) {
            $dashboard->assertDontSee($hiddenItem, false);
        }
    }

    public function test_consultant_is_blocked_from_non_consultant_operational_and_admin_workbenches(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();

        // All Consultant clinical pages succeed
        foreach ([
            route('dashboard'),
            route('opd.index'),
            route('emr.cosign.index'),
            route('emr.favorites.index'),
            route('patients.index'),
            route('emergency.index'),
            route('ipd.index'),
            route('cardiology.procedures.index'),
            route('security.two-factor.show'),
        ] as $allowedUrl) {
            $this->actingAs($consultant)->get($allowedUrl)->assertOk();
        }

        // Non-consultant department workbenches & admin consoles return 403 Forbidden
        foreach ([
            route('opd.pre-consult.index'),
            route('nursing.index'),
            route('icu.index'),
            route('lis.index'),
            route('ris.index'),
            route('pharmacy.index'),
            route('billing.index'),
            route('reports.index'),
            route('ai.index'),
            route('admin.audit-logs.index'),
        ] as $forbiddenUrl) {
            $this->actingAs($consultant)->get($forbiddenUrl)->assertForbidden();
        }
    }
}
