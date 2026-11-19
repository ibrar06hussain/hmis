<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Pacs\Models\PacsViewerSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Phase 6 Orthanc DICOM Server + OHIF Viewer PACS Gateway.
 *
 * Operates completely over internal Hospital LAN using DICOMweb QIDO-RS and short-lived
 * audited OHIF viewer launch sessions. Never stores DICOM pixel data in HMIS relational DB.
 */
final class OrthancPacsGateway implements PacsGatewayInterface
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('hmis.pacs.enabled', false)
            && ! empty(config('hmis.pacs.ohif_viewer_url'));
    }

    public function buildViewerUrl(ImagingStudy $study, int $userId): ?string
    {
        if (! $this->isEnabled() || empty($study->study_instance_uid)) {
            return null;
        }

        $ephemeralToken = Str::random(40);
        $now = Carbon::now('UTC');

        $session = PacsViewerSession::query()->create([
            'imaging_study_id' => $study->id,
            'user_id' => $userId,
            'study_instance_uid' => $study->study_instance_uid,
            'launch_token_hash' => hash('sha256', $ephemeralToken),
            'ip_address' => app()->bound('request') ? request()->ip() : null,
            'launched_at' => $now,
            'expires_at' => $now->copy()->addMinutes(30),
        ]);

        $actor = User::query()->find($userId);
        $this->auditLogger->log(
            action: 'pacs.viewer_session_issued',
            actor: $actor,
            subject: $session,
            patientId: $study->patient_id,
            newValues: [
                'accession_number' => $study->accession_number,
                'study_instance_uid' => $study->study_instance_uid,
                'expires_at' => $session->expires_at->toIso8601String(),
            ],
        );

        $base = rtrim((string) config('hmis.pacs.ohif_viewer_url'), '/');

        return $base.'/viewer?'.http_build_query([
            'StudyInstanceUIDs' => $study->study_instance_uid,
            'token' => $ephemeralToken,
        ]);
    }

    /**
     * Query Orthanc DICOMweb QIDO-RS (/dicom-web/studies?AccessionNumber=...) and bind StudyInstanceUID.
     */
    public function syncStudyByAccession(ImagingStudy $study): ?string
    {
        $orthancUrl = rtrim((string) config('hmis.pacs.orthanc_url', ''), '/');
        if ($orthancUrl === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(5)
            ->get("{$orthancUrl}/dicom-web/studies", [
                'AccessionNumber' => $study->accession_number,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        // DICOM JSON tag 0020000D = StudyInstanceUID
        $uid = $payload[0]['0020000D']['Value'][0] ?? null;
        if (! is_string($uid) || trim($uid) === '') {
            return null;
        }

        $study->study_instance_uid = trim($uid);
        $study->pacs_server_key = 'orthanc_main';
        $study->save();

        $this->auditLogger->log(
            action: 'pacs.study_uid_synced',
            actor: null,
            subject: $study,
            patientId: $study->patient_id,
            newValues: [
                'accession_number' => $study->accession_number,
                'study_instance_uid' => $study->study_instance_uid,
            ],
        );

        return $study->study_instance_uid;
    }
}
