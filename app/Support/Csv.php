<?php

namespace App\Support;

/**
 * CSV formula-injection guard. Spreadsheet apps evaluate cells that begin with
 * =, +, -, @, or a leading tab/CR as formulas; prefixing those with a single
 * quote neutralises them while keeping the displayed value readable.
 */
class Csv
{
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** Escape every cell in a row. */
    public static function row(array $row): array
    {
        return array_map(static fn ($v) => self::cell($v), $row);
    }

    /**
     * Safe download filename. Strips everything outside [A-Za-z0-9._-], collapses
     * '..', truncates to 80 chars and appends a UTC date. Prevents path traversal
     * and Content-Disposition header injection (no CR/LF can survive).
     */
    public static function filename(string $base, string $ext = 'csv'): string
    {
        $base = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);

        while (str_contains($base, '..')) {
            $base = str_replace('..', '.', $base);
        }

        $base = trim($base, '-._');
        $base = substr($base === '' ? 'export' : $base, 0, 80);

        $ext = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $ext));
        $ext = $ext === '' ? 'csv' : substr($ext, 0, 8);

        return $base . '-' . gmdate('Ymd') . '.' . $ext;
    }
}
