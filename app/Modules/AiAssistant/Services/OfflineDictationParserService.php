<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

/**
 * 100% Offline Deterministic Cardiology Dictation-to-Structured-Draft Parser.
 *
 * Parses English & Roman-Urdu clinical shorthand dictation into pre-filled Rapid Consult
 * [DRAFT] fields. NEVER persists notes, diagnoses, or prescriptions directly — all outputs
 * require explicit clinician verification and digital signature.
 */
final class OfflineDictationParserService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $transcript): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $transcript) ?? '');
        $lower = strtolower($clean);

        // 1. Vitals extraction (BP 150/90, HR 84, SpO2 97%, Wt 76kg)
        $systolicBp = null;
        $diastolicBp = null;
        if (preg_match('/\b(?:bp|blood\s*pressure)\s*[:=]?\s*(\d{2,3})\s*[\/\-]\s*(\d{2,3})\b/i', $clean, $m)) {
            $systolicBp = (int) $m[1];
            $diastolicBp = (int) $m[2];
        }

        $heartRate = null;
        if (preg_match('/\b(?:hr|heart\s*rate|pulse)\s*[:=]?\s*(\d{2,3})\b/i', $clean, $m)) {
            $heartRate = (int) $m[1];
        }

        $spo2 = null;
        if (preg_match('/\b(?:spo2|o2\s*sat|sat|oxygen)\s*[:=]?\s*(\d{2,3})\s*%?\b/i', $clean, $m)) {
            $spo2 = min(100, (int) $m[1]);
        }

        $weightKg = null;
        if (preg_match('/\b(?:wt|weight)\s*[:=]?\s*(\d{2,3}(?:\.\d)?)\s*(?:kg)?\b/i', $clean, $m)) {
            $weightKg = (float) $m[1];
        }

        // 2. NYHA class extraction
        $nyhaClass = null;
        if (preg_match('/\bnyha\s*(?:class\s*)?(iv|iii|ii|i|[1-4])\b/i', $clean, $m)) {
            $nyhaClass = match (strtoupper($m[1])) {
                '1', 'I' => 'I',
                '2', 'II' => 'II',
                '3', 'III' => 'III',
                '4', 'IV' => 'IV',
                default => null,
            };
        }

        // 3. Chest pain classification (English + Roman Urdu cues: seene mein dard / chaati dard)
        $chestPainType = null;
        if (str_contains($lower, 'stemi') || str_contains($lower, 'acute acs') || str_contains($lower, 'rest pain')) {
            $chestPainType = 'acute_acs';
        } elseif (str_contains($lower, 'typical angina') || str_contains($lower, 'exertional chest pain') || str_contains($lower, 'seene mein dard') || str_contains($lower, 'chaati')) {
            $chestPainType = 'typical_angina';
        } elseif (str_contains($lower, 'atypical')) {
            $chestPainType = 'atypical_angina';
        } elseif (str_contains($lower, 'no chest pain') || str_contains($lower, 'asymptomatic')) {
            $chestPainType = 'none';
        }

        // 4. Risk factors / PMH extraction
        $pmhHits = [];
        $pmhMap = [
            'htn' => 'Hypertension',
            'hypertension' => 'Hypertension',
            'dm' => 'Diabetes Mellitus',
            'diabetes' => 'Diabetes Mellitus',
            'sugar' => 'Diabetes Mellitus',
            'smoker' => 'Smoking history',
            'pci' => 'Prior PCI/Stent',
            'stent' => 'Prior PCI/Stent',
            'cabg' => 'Prior CABG',
            'bypass' => 'Prior CABG',
            'mi' => 'Prior MI',
        ];
        foreach ($pmhMap as $needle => $label) {
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/i', $lower)) {
                $pmhHits[] = $label;
            }
        }
        $pmhText = $pmhHits !== [] ? implode(', ', array_values(array_unique($pmhHits))) : null;

        // 5. Physical exam & ECG cues
        $examParts = [];
        if (str_contains($lower, 's1+s2') || str_contains($lower, 'normal cvs') || str_contains($lower, 'chest clear')) {
            $examParts[] = 'S1+S2 audible, chest clear bilaterally';
        }
        if (str_contains($lower, 'crep') || str_contains($lower, 'crackles')) {
            $examParts[] = 'Bilateral basal crepitations';
        }
        if (str_contains($lower, 'edema') || str_contains($lower, 'swelling') || str_contains($lower, 'sojan')) {
            $examParts[] = 'Pedal edema noted';
        }
        if (str_contains($lower, 'murmur')) {
            $examParts[] = 'Cardiac murmur auscultated';
        }
        $examText = $examParts !== [] ? '[DRAFT] '.implode('; ', $examParts).'.' : null;

        $ecgParts = [];
        if (preg_match('/\bnsr\b|sinus rhythm/i', $clean)) {
            $ecgParts[] = 'Normal sinus rhythm (NSR)';
        }
        if (preg_match('/\bafib\b|atrial fibrillation/i', $clean)) {
            $ecgParts[] = 'Atrial fibrillation';
        }
        if (preg_match('/\blvh\b/i', $clean)) {
            $ecgParts[] = 'LVH by voltage criteria';
        }
        if (str_contains($lower, 'st elevation') || str_contains($lower, 'st depression') || str_contains($lower, 'st-t')) {
            $ecgParts[] = 'ST-T changes noted';
        }
        $ecgSummary = $ecgParts !== [] ? implode(', ', $ecgParts) : null;

        // 6. Follow-up extraction
        $followUp = null;
        if (preg_match('/follow\s*[\-\s]?up\s+(?:in\s+)?(\d+\s*(?:days?|weeks?|months?))/i', $clean, $m)) {
            $followUp = "[DRAFT] Cardiology OPD follow-up in {$m[1]}.";
        }

        // 7. Suggested hospital protocol code (strictly advisory UI button highlight — NEVER auto-prescribed)
        $suggestedOrderSetCode = null;
        if (str_contains($lower, 'dapt') || str_contains($lower, 'post-pci') || str_contains($lower, 'post pci') || str_contains($lower, 'stent')) {
            $suggestedOrderSetCode = 'PROTO-ACS-DAPT';
        } elseif (str_contains($lower, 'hfref') || str_contains($lower, 'heart failure') || str_contains($lower, 'orthopnea')) {
            $suggestedOrderSetCode = 'PROTO-HFREF';
        } elseif (str_contains($lower, 'afib') || str_contains($lower, 'atrial fibrillation') || str_contains($lower, 'warfarin')) {
            $suggestedOrderSetCode = 'PROTO-AFIB';
        } elseif (str_contains($lower, 'angina') || str_contains($lower, 'chest pain') || str_contains($lower, 'seene mein dard')) {
            $suggestedOrderSetCode = 'PROTO-ANGINA-WORKUP';
        } elseif (str_contains($lower, 'htn') || str_contains($lower, 'hypertension')) {
            $suggestedOrderSetCode = 'PROTO-HTN';
        }

        // 8. Chief complaint & assessment drafts (always explicitly watermarked)
        $chiefComplaint = '[DRAFT] '.mb_substr($clean, 0, 140);
        $assessment = '[DRAFT — VERIFY BEFORE SIGNING] '.$clean;

        return [
            'requires_clinician_review' => true,
            'watermark' => '[DRAFT — REQUIRES AUTHORIZED CLINICIAN REVIEW]',
            'chief_complaint' => $chiefComplaint,
            'past_medical_history' => $pmhText,
            'physical_examination' => $examText,
            'ecg_summary' => $ecgSummary,
            'assessment_and_diagnosis' => $assessment,
            'follow_up_instructions' => $followUp,
            'nyha_class' => $nyhaClass,
            'chest_pain_type' => $chestPainType,
            'systolic_bp' => $systolicBp,
            'diastolic_bp' => $diastolicBp,
            'heart_rate' => $heartRate,
            'spo2_percent' => $spo2,
            'weight_kg' => $weightKg,
            'suggested_order_set_code' => $suggestedOrderSetCode,
        ];
    }
}
