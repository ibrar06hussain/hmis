<?php

declare(strict_types=1);

namespace App\Modules\Ris\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Services\DiagnosticSequenceService;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Ris\Models\ImagingCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ImagingOrderService
{
    public function __construct(
        private readonly DiagnosticSequenceService $sequenceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Order a diagnostic imaging examination with an immutable PACS-ready Accession Number.
     * Checks active contrast dye allergies when the modality requires contrast.
     *
     * @return array{study: ImagingStudy, contrast_allergy_warning: bool}
     */
    public function order(
        Encounter $encounter,
        int $catalogId,
        string $priority,
        ?string $clinicalIndication,
        User $actor,
    ): array {
        $catalog = ImagingCatalog::query()->where('is_active', true)->findOrFail($catalogId);
        $patient = $encounter->patient;

        $hasContrastAllergy = $catalog->requires_contrast && $patient->activeAllergies()
            ->where('category', 'contrast_dye')
            ->exists();

        $study = DB::transaction(function () use ($encounter, $catalog, $patient, $priority, $clinicalIndication, $actor, $hasContrastAllergy): ImagingStudy {
            $study = ImagingStudy::query()->create([
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'imaging_catalog_id' => $catalog->id,
                'ordered_by_user_id' => $actor->id,
                'accession_number' => $this->sequenceService->next('ACC'),
                'modality' => $catalog->modality,
                'priority' => $priority,
                'procedure_name' => $catalog->name,
                'clinical_indication' => $clinicalIndication,
                'status' => ImagingStudy::STATUS_ORDERED,
                'study_date' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'ris.order.created',
                actor: $actor,
                subject: $study,
                patientId: $patient->id,
                newValues: [
                    'accession_number' => $study->accession_number,
                    'modality' => $study->modality,
                    'procedure_name' => $study->procedure_name,
                    'contrast_allergy_warning' => $hasContrastAllergy,
                ],
            );

            return $study;
        });

        return [
            'study' => $study,
            'contrast_allergy_warning' => $hasContrastAllergy,
        ];
    }

    public function saveReport(
        ImagingStudy $study,
        string $reportSummary,
        ?string $studyInstanceUid,
        bool $verifyNow,
        User $actor,
    ): ImagingStudy {
        return DB::transaction(function () use ($study, $reportSummary, $studyInstanceUid, $verifyNow, $actor): ImagingStudy {
            $locked = ImagingStudy::query()->whereKey($study->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ImagingStudy::STATUS_VERIFIED) {
                throw new \DomainException('Verified radiology reports cannot be overwritten directly.');
            }

            $now = Carbon::now('UTC');
            $locked->report_summary = trim($reportSummary);
            if ($studyInstanceUid !== null && trim($studyInstanceUid) !== '') {
                $locked->study_instance_uid = trim($studyInstanceUid);
                $locked->pacs_server_key = config('hmis.pacs.enabled') ? 'orthanc_main' : 'orthanc_pending';
            }
            $locked->reported_by_user_id = $actor->id;
            $locked->reported_at = $now;
            $locked->status = $verifyNow ? ImagingStudy::STATUS_VERIFIED : ImagingStudy::STATUS_REPORTED;

            if ($verifyNow) {
                $locked->verified_by_user_id = $actor->id;
                $locked->verified_at = $now;
            }

            $locked->save();

            $this->auditLogger->log(
                action: $verifyNow ? 'ris.report.verified' : 'ris.report.drafted',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: [
                    'accession_number' => $locked->accession_number,
                    'status' => $locked->status,
                    'has_study_uid' => $locked->study_instance_uid !== null,
                ],
            );

            return $locked;
        });
    }
}
