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
}
