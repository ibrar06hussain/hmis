<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Models\FinancialLedgerEntry;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingDeskController extends Controller
{
    public function __construct(
        private readonly BillingService $billingService,
    ) {}

    public function index(): View
    {
        Gate::authorize('manage', Invoice::class);

        $invoices = Invoice::query()
            ->with(['patient', 'items', 'issuedBy'])
            ->orderByDesc('issued_at')
            ->limit(50)
            ->get();

        $ledgerEntries = FinancialLedgerEntry::query()
            ->with(['patient', 'invoice', 'recordedBy'])
            ->orderByDesc('recorded_at')
            ->limit(30)
            ->get();

        $tariffs = ServiceTariff::query()->where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $activePatients = Patient::query()->active()->orderByDesc('id')->limit(100)->get();

        return view('modules.billing.index', [
            'invoices' => $invoices,
            'ledgerEntries' => $ledgerEntries,
            'tariffs' => $tariffs,
            'activePatients' => $activePatients,
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Invoice::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'tariff_id' => ['required', 'integer', 'exists:service_tariffs,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'billing_category' => ['required', Rule::in(['general', 'sehat_card', 'zakat', 'govt_employee', 'indigent_waiver'])],
            'exemption_pkr' => ['nullable', 'numeric', 'min:0'],
            'exemption_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = Patient::query()->findOrFail((int) $validated['patient_id']);

        /** @var User $user */
        $user = $request->user();

        try {
            $invoice = $this->billingService->createInvoice(
                patient: $patient,
                lines: [[
                    'tariff_id' => (int) $validated['tariff_id'],
                    'quantity' => (int) $validated['quantity'],
                ]],
                billingCategory: (string) $validated['billing_category'],
                exemptionPkr: (float) ($validated['exemption_pkr'] ?? 0),
                exemptionReason: $validated['exemption_reason'] ?? null,
                actor: $user,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()
            ->route('billing.index')
            ->with('status', "Invoice {$invoice->invoice_number} issued (Net Payable: PKR ".number_format($invoice->net_payable_pkr, 2).').');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', Invoice::class);

        $validated = $request->validate([
            'amount_pkr' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'sehat_card', 'govt_waiver'])],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $receipt = $this->billingService->recordPayment(
                invoice: $invoice,
                amountPkr: (float) $validated['amount_pkr'],
                paymentMethod: (string) $validated['payment_method'],
                referenceNote: $validated['reference_note'] ?? null,
                cashier: $user,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()
            ->route('billing.index')
            ->with('status', "Payment recorded — Immutable Receipt {$receipt->entry_number} (PKR ".number_format(abs($receipt->amount_pkr), 2).').');
    }

    public function voidInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', Invoice::class);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->billingService->voidInvoice($invoice, (string) $validated['reason'], $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()
            ->route('billing.index')
            ->with('warning', "Invoice {$invoice->invoice_number} voided with compensating ledger reversal.");
    }
}
