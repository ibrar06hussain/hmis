<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

/**
 * Provider-Neutral Local LAN Speech-to-Text Contract (Phase 7 / Voice Dictation).
 * Implementations MUST run strictly on-premises (e.g., hospital LAN Whisper server) —
 * never transmit patient voice audio or PHI to public cloud speech APIs.
 */
interface VoiceTranscriptionGatewayInterface
{
    public function isEnabled(): bool;

    public function transcribeLocalAudio(string $audioFilePath, string $languageHint = 'en'): string;
}
