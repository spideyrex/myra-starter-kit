<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The IANA timezone list, in one place.
 *
 * Labels carry the CURRENT UTC offset, so the picker reads
 * `(UTC+08:00) Asia/Kuala_Lumpur`. Offsets move with DST — this is a display
 * aid, never something to store or compare against.
 */
final class Timezones
{
    /** @var array<int,string>|null */
    private static ?array $identifiers = null;

    /** @return array<int,string> */
    public static function identifiers(): array
    {
        return self::$identifiers ??= DateTimeZone::listIdentifiers();
    }

    public static function isValid(string $timezone): bool
    {
        return $timezone !== '' && in_array($timezone, self::identifiers(), true);
    }

    /** Current UTC offset in seconds, 0 for anything unrecognised. */
    public static function offset(string $timezone): int
    {
        try {
            return (new DateTimeZone($timezone))->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        } catch (Throwable) {
            return 0;
        }
    }

    /** `+08:00`, `-03:30`, `+00:00`. */
    public static function formatOffset(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * Select options, ordered west to east then alphabetically.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::identifiers() as $identifier) {
            $offset = self::offset($identifier);

            $options[] = [
                'value' => $identifier,
                'label' => '(UTC'.self::formatOffset($offset).') '.str_replace('_', ' ', $identifier),
                'offset' => $offset,
            ];
        }

        usort($options, fn (array $a, array $b) => [$a['offset'], $a['value']] <=> [$b['offset'], $b['value']]);

        return array_map(
            fn (array $o) => ['value' => $o['value'], 'label' => $o['label']],
            $options,
        );
    }

    /** Test seam — the identifier list is memoised for the request. */
    public static function flush(): void
    {
        self::$identifiers = null;
    }
}
