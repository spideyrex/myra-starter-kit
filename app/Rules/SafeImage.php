<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Magic-byte sniff. The client-supplied extension is ignored entirely and SVG
 * is rejected outright — it is script-bearing markup on a same-origin disk.
 */
class SafeImage implements ValidationRule
{
    /** mime => canonical extension */
    public const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    private const CAPS = [
        'favicon' => ['bytes' => 262144, 'w' => 1024, 'h' => 1024],
        'mark' => ['bytes' => 1048576, 'w' => 2048, 'h' => 2048],
        'og_image' => ['bytes' => 4194304, 'w' => 4096, 'h' => 4096],
        'default' => ['bytes' => 2097152, 'w' => 4096, 'h' => 4096],
    ];

    public function __construct(private readonly string $slot = 'logo') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('brand.upload.invalid'));

            return;
        }

        $caps = self::CAPS[$this->slot] ?? self::CAPS['default'];
        $bytes = @file_get_contents($value->getRealPath());

        if ($bytes === false || $bytes === '') {
            $fail(__('brand.upload.invalid'));

            return;
        }

        if (strlen($bytes) > $caps['bytes']) {
            $fail(__('brand.upload.tooLarge', ['kb' => (int) ($caps['bytes'] / 1024)]));

            return;
        }

        $mime = self::sniff($bytes);

        if ($mime === 'image/svg+xml' || self::looksLikeSvg($bytes)) {
            $fail(__('brand.upload.svgRejected'));

            return;
        }

        if ($mime === null || ! isset(self::ALLOWED[$mime])) {
            $fail(__('brand.upload.unsupported'));

            return;
        }

        // .ico carries no getimagesize() geometry; its size cap is the byte cap.
        if (self::ALLOWED[$mime] !== 'ico') {
            $size = @getimagesizefromstring($bytes);

            if ($size === false) {
                $fail(__('brand.upload.unsupported'));

                return;
            }

            if ($size[0] > $caps['w'] || $size[1] > $caps['h']) {
                $fail(__('brand.upload.tooLarge', ['kb' => (int) ($caps['bytes'] / 1024)]));
            }
        }
    }

    public static function extensionFor(string $bytes): ?string
    {
        $mime = self::sniff($bytes);

        return $mime !== null ? (self::ALLOWED[$mime] ?? null) : null;
    }

    public static function sniff(string $bytes): ?string
    {
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return self::canonical($mime, $bytes);
                }
            }
        }

        return self::canonical('', $bytes);
    }

    /** finfo reports .ico inconsistently across builds; the header is authoritative. */
    private static function canonical(string $mime, string $bytes): ?string
    {
        if (str_starts_with($bytes, "\x00\x00\x01\x00")) {
            return 'image/x-icon';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($bytes, "\xff\xd8\xff")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return $mime !== '' ? $mime : null;
    }

    private static function looksLikeSvg(string $bytes): bool
    {
        $head = strtolower(substr($bytes, 0, 512));

        return str_contains($head, '<svg') || str_contains($head, '<?xml') && str_contains($head, 'svg');
    }
}
