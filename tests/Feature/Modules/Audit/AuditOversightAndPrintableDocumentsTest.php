<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Audit;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Iam\Services\BreakGlassService;
use App\Modules\Ipd\Services\IpdAdmissionService;
use App\Modules\Organization\Models\Bed;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditOversightAndPrintableDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_two_factor_setup_renders_offline_inline_svg_qr_code(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();

        $html = $this->actingAs($consultant)
            ->get(route('security.two-factor.show'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('otpauth://totp/')
            ->getContent();

        $this->assertStringNotContainsString('chart.googleapis.com', (string) $html);
        $this->assertStringNotContainsString('api.qrserver.com', (string) $html);
    }

    public function test_printable_bilingual_discharge_summary_opd_slip_and_billing_receipt_enforce_rbac_and_render_urdu_english(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $reception = User::query()->where('username', 'reception')->firstOrFail();
        $billing = User::query()->where('username', 'billing')->firstOrFail();
        $itAdmin = User::query()->where('username', 'it_admin')->firstOrFail();
        $patient = Patient::query()->firstOrFail();

        // 1. Printable Bilingual Discharge Summary
        $bed = Bed::query()->where('status', 'available')->firstOrFail();
        $ipdService = app(IpdAdmissionService::class);
        $admission = $ipdService->admit([
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Acute Inferior Wall STEMI',
        ], $consultant);

        $discharged = $ipdService->discharge($admission, [
            'discharge_status' => 'discharged',
            'discharge_condition' => 'improved',
            'discharge_summary' => 'Successful primary PCI to RCA with DES. Uneventful CCU recovery.',
            'follow_up_plan' => 'Cardiology OPD in 10 days with ECG and renal function test.',
        ], $consultant);

        $this->actingAs($consultant)
            ->get(route('ipd.admissions.discharge-summary', $discharged))
            ->assertOk()
            ->assertSee($discharged->admission_number)
            ->assertSee('کارڈیک ہسپتال گلگت')
            ->assertSee('مریض کی ڈسچارج سمری اور ہدایات')
            ->assertSee('Successful primary PCI to RCA with DES')
            ->assertSee('<svg', false);

        // Non-clinical IT Administrator and Billing Officer must not access Discharge Summary
        $this->actingAs($itAdmin)
            ->get(route('ipd.admissions.discharge-summary', $discharged))
            ->assertForbidden();
        $this->actingAs($billing)
            ->get(route('ipd.admissions.discharge-summary', $discharged))
            ->assertForbidden();

        // 2. Printable Bilingual OPD Token & Visit Slip
        $appointment = Appointment::query()->firstOrFail();
        $this->actingAs($reception)
            ->get(route('opd.appointments.slip', $appointment))
            ->assertOk()
            ->assertSee($appointment->formattedToken())
            ->assertSee('او پی ڈی ٹوکن اور معائنہ پرچی')
            ->assertSee('<svg', false);

        $this->actingAs($itAdmin)
            ->get(route('opd.appointments.slip', $appointment))
            ->assertForbidden();

        // 3. Printable Bilingual Billing / OPD Receipt
        $tariff = ServiceTariff::query()->firstOrFail();
        $billingService = app(BillingService::class);
        $invoice = $billingService->createInvoice(
            patient: $patient,
            lines: [['tariff_id' => $tariff->id, 'quantity' => 1]],
            billingCategory: 'sehat_card',
            exemptionPkr: 500.0,
            exemptionReason: 'Sehat Sahulat Card Waiver',
            actor: $billing,
        );

        $this->actingAs($billing)
            ->get(route('billing.invoices.receipt', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('سرکاری ہسپتال فیس رسید')
            ->assertSee('Sehat Sahulat Card Waiver')
            ->assertSee('<svg', false);

        // Clinician and IT Admin cannot open Billing Receipt desk
        $this->actingAs($consultant)
            ->get(route('billing.invoices.receipt', $invoice))
            ->assertForbidden();
        $this->actingAs($itAdmin)
            ->get(route('billing.invoices.receipt', $invoice))
            ->assertForbidden();
    }

    public function test_audit_oversight_console_rbac_filtering_and_immediate_break_glass_revocation(): void
    {
        $ms = User::query()->where('username', 'ms')->firstOrFail();
        $itAdmin = User::query()->where('username', 'it_admin')->firstOrFail();
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $reception = User::query()->where('username', 'reception')->firstOrFail();
        $patient = Patient::query()->firstOrFail();

        // Unauthorized clinical & clerical roles are blocked
        $this->actingAs($consultant)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($reception)->get(route('admin.audit-logs.index'))->assertForbidden();

        // Create an active break-glass emergency grant
        $breakGlassService = app(BreakGlassService::class);
        $grant = $breakGlassService->grant(
            user: $consultant,
            patient: $patient,
            reasonCode: 'emergency_consultation',
            justification: 'Urgent CCU arrhythmia consult on cross-department patient',
        );
        $this->assertTrue($breakGlassService->hasActiveGrant($consultant, $patient));

        // Both Medical Superintendent and IT Administrator can view the console & filter logs
        $this->actingAs($itAdmin)
            ->get(route('admin.audit-logs.index', ['preset' => 'break_glass', 'mrn' => $patient->mrn]))
            ->assertOk()
            ->assertSee('security.break_glass.granted')
            ->assertSee($patient->mrn)
            ->assertSee('ACTIVE');

        // Consultant cannot revoke their own or others' break-glass via admin endpoint
        $this->actingAs($consultant)
            ->post(route('admin.break-glass.revoke', $grant), ['reason' => 'Attempting unauthorized self-revoke'])
            ->assertForbidden();

        // Medical Superintendent revokes the active break-glass override early
        $this->actingAs($ms)
            ->post(route('admin.break-glass.revoke', $grant), ['reason' => 'Emergency consult completed; routine department transfer finished'])
            ->assertRedirect(route('admin.audit-logs.index'));

        $grant->refresh();
        $this->assertFalse($grant->isActive());
        $this->assertFalse($breakGlassService->hasActiveGrant($consultant, $patient));

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'security.break_glass.revoked')
                ->where('actor_user_id', $ms->id)
                ->where('patient_id', $patient->id)
                ->exists()
        );
    }
}
