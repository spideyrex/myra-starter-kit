<?php

namespace App\Support;

/**
 * The allowlist for authored link targets.
 *
 * Lifted out of HtmlSanitizer so the page builder's `url` fields and the
 * sanitizer share one decision. Anything not on the list is refused —
 * javascript:, data:, vbscript: and protocol-relative //host all fail.
 */
final class UrlGuard
{
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function isSafe(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $trimmed = trim($url);

        if ($trimmed === '') {
            return true;
        }

        // //host and /\host are protocol-relative in a browser.
        if (preg_match('#^[/\\\\]{2}#', $trimmed) === 1) {
            return false;
        }

        if ($trimmed[0] === '/' || $trimmed[0] === '#') {
            return true;
        }

        if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $trimmed, $m) === 1) {
            return in_array(strtolower($m[1]), self::SCHEMES, true);
        }

        return false;
    }

    public static function safe(?string $url, string $fallback = ''): string
    {
        return self::isSafe($url) ? trim((string) $url) : $fallback;
    }

    /** True when the value carries an explicit scheme. */
    public static function hasScheme(?string $url): bool
    {
        return $url !== null && preg_match('#^\s*[a-z][a-z0-9+.\-]*:#i', $url) === 1;
    }
}
