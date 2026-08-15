<?php

namespace App\Admin\Import;

/**
 * Server-side auto-mapping, four passes in order of confidence:
 * exact -> normalised (case/space/hyphen) -> declared guess() aliases -> Levenshtein <= 2.
 * A header is consumed by at most one column.
 */
final class HeaderMapper
{
    /**
     * @param  string[]  $headers
     * @return array<string,string> column name => csv header ('' when unmatched)
     */
    public static function guess(ImportDefinition $definition, array $headers): array
    {
        $mapping = [];
        $taken = [];

        $normalised = [];
        foreach ($headers as $h) {
            $normalised[$h] = ImportColumn::normalise($h);
        }

        foreach ([1, 2, 3, 4] as $pass) {
            foreach ($definition->getColumns() as $column) {
                if (! empty($mapping[$column->name()])) {
                    continue;
                }

                $match = self::matchFor($column, $headers, $normalised, $taken, $pass);

                if ($match !== null) {
                    $mapping[$column->name()] = $match;
                    $taken[$match] = true;
                }
            }
        }

        foreach ($definition->getColumns() as $column) {
            $mapping[$column->name()] ??= '';
        }

        return $mapping;
    }

    private static function matchFor(
        ImportColumn $column,
        array $headers,
        array $normalised,
        array $taken,
        int $pass,
    ): ?string {
        $target = ImportColumn::normalise($column->name());
        $labelTarget = ImportColumn::normalise($column->getLabel());

        foreach ($headers as $header) {
            if (isset($taken[$header])) {
                continue;
            }

            $n = $normalised[$header];

            $hit = match ($pass) {
                1 => $header === $column->name(),
                2 => $n === $target || $n === $labelTarget,
                3 => in_array($n, $column->aliases(), true),
                4 => $n !== '' && levenshtein($n, $target) <= 2,
                default => false,
            };

            if ($hit) {
                return $header;
            }
        }

        return null;
    }
}
