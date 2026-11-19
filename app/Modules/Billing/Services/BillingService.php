<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Billing\Models\FinancialLedgerEntry;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Lis\Services\DiagnosticSequenceService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BillingService
{
    public function __construct(
        private readonly DiagnosticSequenceService $sequenceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Issue a hospital invoice with tariff lines, optional government/Sehat Card/Zakat exemption,
     * and append-only financial ledger entries.
     *
     * @param  list<array{tariff_id: int, quantity: int}>  $lines
     */
    public function createInvoice(
        Patient $patient,
        array $lines,
        string $billingCategory,
        float $exemptionPkr,
        ?string $exemptionReason,
        User $actor,
        ?int $encounterId = null,
    ): Invoice {
        $canonicalPatient = $patient->canonicalPatient();
        if ($lines === []) {
            throw new \InvalidArgumentException('An invoice must contain at least one billable service line.');
        }

        $tariffs = ServiceTariff::query()
            ->whereIn('id', array_column($lines, 'tariff_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($tariffs->isEmpty()) {
            throw new \InvalidArgumentException('No valid active hospital service tariffs selected.');
        }

        return DB::transaction(function () use ($canonicalPatient, $lines, $tariffs, $billingCategory, $exemptionPkr, $exemptionReason, $actor, $encounterId): Invoice {
            $now = Carbon::now('UTC');
            $subtotal = 0.0;
            $preparedLines = [];

            foreach ($lines as $line) {
                /** @var ServiceTariff|null $tariff */
                $tariff = $tariffs->get((int) $line['tariff_id']);
                if ($tariff === null) {
                    continue;
                }
                $qty = max(1, (int) $line['quantity']);
                $lineTotal = round($tariff->unit_price_pkr * $qty, 2);
                $subtotal += $lineTotal;

                $preparedLines[] = [
                    'service_tariff_id' => $tariff->id,
                    'description' => $tariff->name,
                    'quantity' => $qty,
                    'unit_price_pkr' => $tariff->unit_price_pkr,
                    'line_total_pkr' => $lineTotal,
                ];
            }

            // Full automatic waiver for Sehat Card / Indigent waiver if user didn't specify a lower partial amount
            $appliedExemption = round(min($subtotal, max(0.0, $exemptionPkr)), 2);
            if ($appliedExemption > 0 && trim((string) $exemptionReason) === '' && $billingCategory === 'general') {
                throw new \InvalidArgumentException('Exemption reason is required when applying a fee waiver.');
            }

            $netPayable = round(max(0.0, $subtotal - $appliedExemption), 2);
            $status = $netPayable <= 0.0 ? Invoice::STATUS_PAID : Invoice::STATUS_ISSUED;

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->sequenceService->next('INV'),
                'patient_id' => $canonicalPatient->id,
                'encounter_id' => $encounterId,
                'billing_category' => $billingCategory,
                'status' => $status,
                'subtotal_pkr' => $subtotal,
                'exemption_pkr' => $appliedExemption,
                'net_payable_pkr' => $netPayable,
                'paid_pkr' => 0.0,
                'balance_pkr' => $netPayable,
                'exemption_reason' => $exemptionReason ? trim($exemptionReason) : null,
                'issued_by_user_id' => $actor->id,
                'issued_at' => $now,
                'version' => 1,
            ]);

            foreach ($preparedLines as $item) {
                InvoiceItem::query()->create(['invoice_id' => $invoice->id, ...$item]);
            }

            // Append immutable charge entry
            FinancialLedgerEntry::query()->create([
                'entry_number' => $this->sequenceService->next('CHG'),
                'invoice_id' => $invoice->id,
                'patient_id' => $canonicalPatient->id,
                'entry_type' => 'charge',
                'payment_method' => null,
                'amount_pkr' => $subtotal,
                'reference_note' => "Invoice {$invoice->invoice_number} issued ({$billingCategory})",
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => $now,
            ]);

            if ($appliedExemption > 0) {
                FinancialLedgerEntry::query()->create([
                    'entry_number' => $this->sequenceService->next('EXM'),
                    'invoice_id' => $invoice->id,
                    'patient_id' => $canonicalPatient->id,
                    'entry_type' => 'exemption',
                    'payment_method' => $billingCategory === 'sehat_card' ? 'sehat_card' : 'govt_waiver',
                    'amount_pkr' => -$appliedExemption,
                    'reference_note' => $exemptionReason ?: "Government / {$billingCategory} exemption",
                    'recorded_by_user_id' => $actor->id,
                    'recorded_at' => $now,
                ]);
            }

            $this->auditLogger->log(
                action: 'billing.invoice.issued',
                actor: $actor,
                subject: $invoice,
                patientId: $canonicalPatient->id,
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'billing_category' => $billingCategory,
                    'subtotal_pkr' => $subtotal,
                    'exemption_pkr' => $appliedExemption,
                    'net_payable_pkr' => $netPayable,
                ],
            );

            return $invoice->fresh(['items', 'ledgerEntries', 'patient']);
        });
    }

    /**
     * Collect full or partial payment against an issued invoice and append an immutable receipt entry.
     */
    public function recordPayment(
        Invoice $invoice,
        float $amountPkr,
        string $paymentMethod,
        ?string $referenceNote,
        User $cashier,
    ): FinancialLedgerEntry {
        $amount = round($amountPkr, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $amount, $paymentMethod, $referenceNote, $cashier): FinancialLedgerEntry {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOIDED], true)) {
                throw new \DomainException("Cannot record payment against an invoice with status '{$locked->status}'.");
            }

            if ($amount > round($locked->balance_pkr + 0.001, 2)) {
                throw new \DomainException("Payment of PKR {$amount} exceeds remaining balance of PKR {$locked->balance_pkr}.");
            }

            $now = Carbon::now('UTC');
            $entry = FinancialLedgerEntry::query()->create([
                'entry_number' => $this->sequenceService->next('RCP'),
                'invoice_id' => $locked->id,
                'patient_id' => $locked->patient_id,
                'entry_type' => 'payment',
                'payment_method' => $paymentMethod,
                'amount_pkr' => -$amount,
                'reference_note' => $referenceNote ?: "Payment received for {$locked->invoice_number}",
                'recorded_by_user_id' => $cashier->id,
                'recorded_at' => $now,
            ]);

            $locked->paid_pkr = round($locked->paid_pkr + $amount, 2);
            $locked->balance_pkr = round(max(0.0, $locked->net_payable_pkr - $locked->paid_pkr), 2);
            $locked->status = $locked->balance_pkr <= 0.0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'billing.payment.recorded',
                actor: $cashier,
                subject: $entry,
                patientId: $locked->patient_id,
                newValues: [
                    'receipt_number' => $entry->entry_number,
                    'invoice_number' => $locked->invoice_number,
                    'amount_pkr' => $amount,
                    'payment_method' => $paymentMethod,
                    'remaining_balance_pkr' => $locked->balance_pkr,
                ],
            );

            return $entry;
        });
    }

    /**
     * Void an invoice by appending a compensating ledger reversal (never deleting original ledger rows).
     */
    public function voidInvoice(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Invoice::STATUS_VOIDED) {
                throw new \DomainException('Invoice is already voided.');
            }

            $now = Carbon::now('UTC');

            if ($locked->balance_pkr > 0) {
                FinancialLedgerEntry::query()->create([
                    'entry_number' => $this->sequenceService->next('REV'),
                    'invoice_id' => $locked->id,
                    'patient_id' => $locked->patient_id,
                    'entry_type' => 'void_reversal',
                    'payment_method' => null,
                    'amount_pkr' => -$locked->balance_pkr,
                    'reference_note' => "Void reversal: {$reason}",
                    'recorded_by_user_id' => $actor->id,
                    'recorded_at' => $now,
                ]);
            }

            $locked->status = Invoice::STATUS_VOIDED;
            $locked->balance_pkr = 0.0;
            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLogger->log(
                action: 'billing.invoice.voided',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['invoice_number' => $locked->invoice_number],
                reason: $reason,
            );

            return $locked;
        });
    }
}
