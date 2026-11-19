<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * 100% Offline Local SVG QR Code Renderer (Zero external HTTP/CDN calls).
 */
final class OfflineQrSvg
{
    public static function render(string $payload, int $size = 200, int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($payload);

        // Strip XML prolog so inline HTML5 embedding stays valid and clean
        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg));
    }
}
