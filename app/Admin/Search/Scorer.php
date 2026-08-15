<?php

namespace App\Admin\Search;

/**
 * Ranking is computed in PHP over a bounded candidate set, so no user input
 * ever reaches an ORDER BY expression.
 *
 *   score        = max over attributes( weight × matchKind ) + recencyBoost
 *   matchKind    = exact 1.0 | prefix 0.7 | word-boundary 0.5 | substring 0.3
 *   recencyBoost = recencyWeight × exp(-ageDays / 30)
 */
final class Scorer
{
    public static function matchKind(string $haystack, string $needle): float
    {
        if ($needle === '' || $haystack === '') {
            return 0.0;
        }

        $h = mb_strtolower($haystack);
        $n = mb_strtolower($needle);

        if ($h === $n) {
            return 1.0;
        }

        $pos = mb_strpos($h, $n);
        if ($pos === false) {
            return 0.0;
        }

        if ($pos === 0) {
            return 0.7;
        }

        $before = mb_substr($h, $pos - 1, 1);

        return preg_match('/[\s\-_.,\/@]/u', $before) === 1 ? 0.5 : 0.3;
    }

    public static function recencyBoost(?string $timestamp, float $weight): float
    {
        if ($timestamp === null || $weight <= 0.0) {
            return 0.0;
        }

        $time = strtotime($timestamp);
        if ($time === false) {
            return 0.0;
        }

        $ageDays = max(0.0, (time() - $time) / 86400);

        return $weight * exp(-$ageDays / 30);
    }

    /**
     * Offset ranges for client-side <mark> rendering. Offsets are JS string
     * indices (UTF-16 code units) so the client never re-parses HTML.
     *
     * @return array<int,array{field:string,start:int,length:int}>
     */
    public static function matchRanges(string $field, string $text, string $needle): array
    {
        if ($needle === '' || $text === '') {
            return [];
        }

        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return [];
        }

        return [[
            'field' => $field,
            'start' => self::utf16Length(mb_substr($text, 0, $pos)),
            'length' => self::utf16Length(mb_substr($text, $pos, mb_strlen($needle))),
        ]];
    }

    /** Code points above the BMP count as two UTF-16 units in JavaScript. */
    public static function utf16Length(string $text): int
    {
        $length = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $length += mb_ord($char, 'UTF-8') > 0xFFFF ? 2 : 1;
        }

        return $length;
    }
}
