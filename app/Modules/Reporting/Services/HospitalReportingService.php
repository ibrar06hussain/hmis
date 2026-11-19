<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Models\BreakGlassLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Ward;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\Medication;
use Illuminate\Support\Carbon;

/**
 * Read-only Hospital Operational & Clinical Governance Analytics Service.
 */
final class HospitalReportingService
{
    /**
     * @return array<string, mixed>
     */
    public function executiveSummary(): array
    {
        $nowUtc = Carbon::now('UTC');
        $todayPkt = Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString();

        // 1. Ward & Bed Occupancy
        $totalBeds = Bed::query()->count();
        $occupiedBeds = Bed::query()->where('status', 'occupied')->count();
        $cleaningBeds = Bed::query()->where('status', 'cleaning')->count();
        $occupancyPercent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0.0;

        $wardBreakdown = Ward::query()
            ->with('beds')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Ward $ward): array {
                $total = $ward->beds->count();
                $occupied = $ward->beds->where('status', 'occupied')->count();
                $available = $ward->beds->where('status', 'available')->count();

                return [
                    'code' => $ward->code,
                    'name' => $ward->name,
                    'ward_type' => $ward->ward_type,
                    'total_beds' => $total,
                    'occupied_beds' => $occupied,
                    'available_beds' => $available,
                    'occupancy_percent' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0.0,
                ];
            })->all();

        // 2. Laboratory Turnaround Time (TAT) & Critical Values
        $verifiedOrders = LabOrder::query()
            ->where('status', LabOrder::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->get(['ordered_at', 'verified_at']);

        $avgLabTatMinutes = null;
        if ($verifiedOrders->isNotEmpty()) {
            $totalMinutes = $verifiedOrders->sum(fn (LabOrder $o): float => max(0.0, $o->ordered_at->diffInSeconds($o->verified_at) / 60.0));
            $avgLabTatMinutes = round($totalMinutes / $verifiedOrders->count(), 1);
        }

        // 3. Financial Summary (excluding voided invoices)
        $validInvoices = Invoice::query()->where('status', '!=', Invoice::STATUS_VOIDED);
        $totalBilled = round((float) (clone $validInvoices)->sum('subtotal_pkr'), 2);
        $totalExemptions = round((float) (clone $validInvoices)->sum('exemption_pkr'), 2);
        $totalCollected = round((float) (clone $validInvoices)->sum('paid_pkr'), 2);
        $totalOutstanding = round((float) (clone $validInvoices)->sum('balance_pkr'), 2);

        return [
            'generated_at_utc' => $nowUtc->toIso8601String(),
            'patients' => [
                'active_total' => Patient::query()->active()->count(),
                'unverified_emergency' => Patient::query()->active()->where('identity_verification_status', 'unverified')->count(),
            ],
            'beds' => [
                'total' => $totalBeds,
                'occupied' => $occupiedBeds,
                'cleaning' => $cleaningBeds,
                'available' => Bed::query()->where('status', 'available')->count(),
                'occupancy_percent' => $occupancyPercent,
                'wards' => $wardBreakdown,
            ],
            'clinical_volume' => [
                'opd_appointments_today' => Appointment::query()->whereDate('appointment_date', $todayPkt)->count(),
                'active_inpatients' => IpdAdmission::query()->where('status', IpdAdmission::STATUS_ADMITTED)->count(),
                'emergency_pending' => EmergencyTriage::query()->where('disposition', 'pending')->count(),
                'code_stemi_total' => EmergencyTriage::query()->where('stemi_alert', true)->count(),
            ],
            'diagnostics' => [
                'lab_orders_pending' => LabOrder::query()->whereNotIn('status', [LabOrder::STATUS_VERIFIED, LabOrder::STATUS_CANCELLED])->count(),
                'lab_orders_verified' => $verifiedOrders->count(),
                'lab_avg_tat_minutes' => $avgLabTatMinutes,
                'lab_unacknowledged_critical' => LabOrderItem::query()->where('is_critical', true)->whereNull('critical_acknowledged_at')->count(),
                'imaging_total' => ImagingStudy::query()->count(),
                'imaging_pacs_linked' => ImagingStudy::query()->whereNotNull('study_instance_uid')->count(),
            ],
            'pharmacy' => [
                'low_stock_medications' => Medication::query()->where('is_active', true)->whereColumn('stock_quantity', '<=', 'reorder_level')->count(),
                'high_alert_medications' => Medication::query()->where('is_active', true)->where('is_high_alert', true)->count(),
            ],
            'financial' => [
                'total_billed_pkr' => $totalBilled,
                'total_exemptions_pkr' => $totalExemptions,
                'total_collected_pkr' => $totalCollected,
                'total_outstanding_pkr' => $totalOutstanding,
            ],
            'governance' => [
                'active_break_glass' => BreakGlassLog::query()->where('expires_at', '>', $nowUtc)->count(),
                'audit_events_24h' => AuditLog::query()->where('occurred_at', '>=', $nowUtc->copy()->subHours(24))->count(),
            ],
        ];
    }
}
