<?php

declare(strict_types=1);

namespace App\Modules\Cardiology\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cardiology\Models\CardiacProcedure;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Services\DiagnosticSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CardiacProcedureService
{
    public function __construct(
        private readonly DiagnosticSequenceService $sequenceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Schedule a Cath Lab PCI / Angiography or Cardiac Surgery / OT procedure.
     *
     * @param  array<string, mixed>  $data
     * @return array{procedure: CardiacProcedure, contrast_allergy_warning: bool}
     */
    public function schedule(Encounter $encounter, array $data, User $actor): array
    {
        $patient = $encounter->patient;
        $category = (string) $data['category'];

        $contrastCategories = ['cath_lab_pci', 'coronary_angiography'];
        $hasContrastAllergy = in_array($category, $contrastCategories, true)
            && $patient->activeAllergies()->where('category', 'contrast_dye')->exists();

        $procedure = DB::transaction(function () use ($encounter, $patient, $data, $category, $actor, $hasContrastAllergy): CardiacProcedure {
            $procedure = CardiacProcedure::query()->create([
                'procedure_number' => $this->sequenceService->next('PROC'),
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'category' => $category,
                'procedure_name' => trim((string) $data['procedure_name']),
                'theatre_or_lab' => trim((string) $data['theatre_or_lab']),
                'status' => CardiacProcedure::STATUS_SCHEDULED,
                'lead_operator_staff_id' => ! empty($data['lead_operator_staff_id']) ? (int) $data['lead_operator_staff_id'] : $actor->staffProfile?->id,
                'access_site' => $data['access_site'] ?? null,
                'scheduled_at' => ! empty($data['scheduled_at']) ? Carbon::parse((string) $data['scheduled_at']) : Carbon::now('UTC'),
                'version' => 1,
            ]);

            $this->auditLogger->log(
                action: 'cardiology.procedure.scheduled',
                actor: $actor,
                subject: $procedure,
                patientId: $patient->id,
                newValues: [
                    'procedure_number' => $procedure->procedure_number,
                    'category' => $category,
                    'theatre_or_lab' => $procedure->theatre_or_lab,
                    'contrast_allergy_warning' => $hasContrastAllergy,
                ],
            );

            return $procedure;
        });

        return [
            'procedure' => $procedure,
            'contrast_allergy_warning' => $hasContrastAllergy,
        ];
    }

    /**
     * Complete and digitally sign a Cath Lab / Cardiac Surgery operative record.
     * Enforces mandatory WHO Surgical Safety Checklist confirmation before sign-off.
     *
     * @param  array<string, mixed>  $data
     */
    public function completeAndSign(CardiacProcedure $procedure, array $data, User $signer): CardiacProcedure
    {
        if (! (bool) ($data['who_checklist_completed'] ?? false)) {
            throw new \DomainException('WHO Surgical / Cath Lab Safety Checklist must be confirmed before completing and signing the procedure record.');
        }

        return DB::transaction(function () use ($procedure, $data, $signer): CardiacProcedure {
            $locked = CardiacProcedure::query()->whereKey($procedure->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CardiacProcedure::STATUS_COMPLETED && $locked->signed_at !== null) {
                throw new \DomainException('Signed cardiac procedure records cannot be modified directly.');
            }

            $now = Carbon::now('UTC');
            $stents = [];
            if (! empty($data['stent_summary'])) {
                $stents = array_values(array_filter(array_map('trim', explode("\n", (string) $data['stent_summary']))));
            }

            $grafts = [];
            if (! empty($data['graft_summary'])) {
                $grafts = array_values(array_filter(array_map('trim', explode("\n", (string) $data['graft_summary']))));
            }

            $locked->fill([
                'status' => CardiacProcedure::STATUS_COMPLETED,
                'who_checklist_completed' => true,
                'access_site' => $data['access_site'] ?? $locked->access_site,
                'contrast_volume_ml' => isset($data['contrast_volume_ml']) && $data['contrast_volume_ml'] !== '' ? (int) $data['contrast_volume_ml'] : null,
                'fluoroscopy_minutes' => isset($data['fluoroscopy_minutes']) && $data['fluoroscopy_minutes'] !== '' ? (float) $data['fluoroscopy_minutes'] : null,
                'stents_implanted' => $stents !== [] ? $stents : null,
                'grafts_performed' => $grafts !== [] ? $grafts : null,
                'operative_findings' => trim((string) $data['operative_findings']),
                'complications' => isset($data['complications']) && trim((string) $data['complications']) !== '' ? trim((string) $data['complications']) : null,
                'post_op_orders' => $data['post_op_orders'] ?? null,
                'completed_at' => $now,
                'signed_by_user_id' => $signer->id,
                'signed_at' => $now,
                'version' => $locked->version + 1,
            ]);
            $locked->save();

            $this->auditLogger->log(
                action: 'cardiology.procedure.signed',
                actor: $signer,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: [
                    'procedure_number' => $locked->procedure_number,
                    'category' => $locked->category,
                    'who_checklist_completed' => true,
                    'stents_count' => count($stents),
                ],
            );

            return $locked->fresh(['patient', 'encounter', 'leadOperator.user', 'signedBy']);
        });
    }
}
