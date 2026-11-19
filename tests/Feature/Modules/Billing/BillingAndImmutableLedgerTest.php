<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Billing;

use App\Models\User;
use App\Modules\Billing\Models\FinancialLedgerEntry;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\PharmacyAndBillingCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingAndImmutableLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $billingOfficer;

    private User $consultant;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
        $this->seed(PharmacyAndBillingCatalogSeeder::class);

        $this->billingOfficer = User::query()->create([
            'name' => 'Accounts Officer',
            'username' => 'bill_off',
            'email' => 'bill@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->billingOfficer->assignRole('billing_officer');

        $this->consultant = User::query()->create([
            'name' => 'Clinician',
            'username' => 'bill_doc',
            'email' => 'billdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000975',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Billing Test Patient',
            'sex' => 'female',
            'date_of_birth' => '1970-01-01',
        ]);
    }

    public function test_invoice_exemption_payment_overpayment_guard_and_ledger_immutability(): void
    {
        $echoTariff = ServiceTariff::query()->where('code', 'IMG-TTE')->firstOrFail(); // PKR 2500

        // Clinician cannot access billing desk (403)
        $this->actingAs($this->consultant)->get('/billing')->assertForbidden();

        // Issue invoice with PKR 500 Zakat exemption -> Net Payable PKR 2000
        $this->actingAs($this->billingOfficer)->post('/billing/invoices', [
            'patient_id' => $this->patient->id,
            'tariff_id' => $echoTariff->id,
            'quantity' => 1,
            'billing_category' => 'zakat',
            'exemption_pkr' => 500,
            'exemption_reason' => 'Zakat committee partial waiver #ZK-2026-14',
        ])->assertRedirect(route('billing.index'));

        $invoice = Invoice::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^INV-\d{4}-000001$/', $invoice->invoice_number);
        $this->assertEquals(2500.0, $invoice->subtotal_pkr);
        $this->assertEquals(500.0, $invoice->exemption_pkr);
        $this->assertEquals(2000.0, $invoice->net_payable_pkr);
        $this->assertEquals(2000.0, $invoice->balance_pkr);

        // Overpayment (PKR 2500 > balance 2000) MUST be rejected
        $this->actingAs($this->billingOfficer)->post(route('billing.invoices.pay', $invoice), [
            'amount_pkr' => 2500,
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('payment');

        // Partial payment PKR 1200 -> partially_paid, balance 800
        $this->actingAs($this->billingOfficer)->post(route('billing.invoices.pay', $invoice), [
            'amount_pkr' => 1200,
            'payment_method' => 'cash',
        ])->assertRedirect(route('billing.index'));

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertEquals(800.0, $invoice->balance_pkr);

        // Remaining payment PKR 800 -> paid, balance 0
        $this->actingAs($this->billingOfficer)->post(route('billing.invoices.pay', $invoice), [
            'amount_pkr' => 800,
            'payment_method' => 'card',
        ])->assertRedirect(route('billing.index'));

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(0.0, $invoice->balance_pkr);
        $this->assertSame(4, FinancialLedgerEntry::query()->where('invoice_id', $invoice->id)->count()); // charge + exemption + 2 payments

        // IMMUTABLE FINANCIAL LEDGER: Updating or deleting an entry MUST throw LogicException
        $entry = FinancialLedgerEntry::query()->firstOrFail();
        try {
            $entry->amount_pkr = 1.0;
            $entry->save();
            $this->fail('Expected LogicException when attempting to mutate financial ledger entry.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        try {
            $entry->delete();
            $this->fail('Expected LogicException when attempting to delete financial ledger entry.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot be deleted', strtolower($e->getMessage()));
        }
    }
}
