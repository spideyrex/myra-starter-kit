import type { MeasureFormat } from './types';

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

/**
 * Renders a measure value. A null value is "not derivable" (a non-additive
 * aggregate on an Other row, or a gap-filled bucket) and MUST show as an
 * em dash — never 0, never NaN.
 */
export function formatMeasure(
    value: number | null | undefined,
    format: MeasureFormat = 'number',
    decimals = 0,
    locale = 'en',
): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';

    switch (format) {
        case 'currency':
            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            }).format(value);

        case 'percent':
            return `${formatNumber(value, decimals, locale)}%`;

        case 'duration':
            return formatDuration(value);

        case 'bytes':
            return formatBytes(value, decimals);

        default:
            return formatNumber(value, decimals, locale);
    }
}

export function formatNumber(value: number, decimals = 0, locale = 'en'): string {
    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

/** Seconds to a compact h/m/s label. */
function formatDuration(seconds: number): string {
    const abs = Math.abs(Math.round(seconds));
    const sign = seconds < 0 ? '-' : '';
    const h = Math.floor(abs / 3600);
    const m = Math.floor((abs % 3600) / 60);
    const s = abs % 60;

    if (h > 0) return `${sign}${h}h ${m}m`;
    if (m > 0) return `${sign}${m}m ${s}s`;
    return `${sign}${s}s`;
}

function formatBytes(bytes: number, decimals = 0): string {
    const abs = Math.abs(bytes);
    if (abs < 1024) return `${bytes} ${BYTE_UNITS[0]}`;

    const exponent = Math.min(Math.floor(Math.log(abs) / Math.log(1024)), BYTE_UNITS.length - 1);
    const scaled = bytes / Math.pow(1024, exponent);

    return `${scaled.toFixed(Math.max(decimals, 1))} ${BYTE_UNITS[exponent]}`;
}

/** Signed percentage for a delta badge. `null` percent is "n/a". */
export function formatDeltaPercent(percent: number | null, locale = 'en'): string {
    if (percent === null || percent === undefined || !Number.isFinite(percent)) return '—';

    const sign = percent > 0 ? '+' : '';
    return `${sign}${formatNumber(percent, Number.isInteger(percent) ? 0 : 1, locale)}%`;
}
