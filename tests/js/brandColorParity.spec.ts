import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { adjustLightness, hexToOklch, isLightColor } from '@/composables/useThemeColors';

/**
 * The fixture is EMITTED BY THE REAL TypeScript functions, never hand-authored,
 * and tests/Unit/Brand/ColorParityTest asserts App\Brand\Color reproduces it
 * string-for-string. Neither side can drift without the other going red.
 *
 * Regenerate with:  MYRA_WRITE_FIXTURES=1 npx vitest run tests/js/brandColorParity.spec.ts
 */

const FIXTURE = path.resolve(__dirname, 'fixtures/brand-oklch.json');

/** Deterministic sweep — the same 256 colours on every machine. */
function samples(): string[] {
    const out: string[] = [];

    for (let i = 0; i < 256; i++) {
        const r = (i * 97) % 256;
        const g = (i * 57 + 13) % 256;
        const b = (i * 173 + 71) % 256;
        out.push('#' + [r, g, b].map((c) => c.toString(16).padStart(2, '0')).join(''));
    }

    return out;
}

function emit() {
    return {
        generatedBy: 'resources/js/composables/useThemeColors.ts',
        entries: samples().map((hex) => ({
            hex,
            oklch: hexToOklch(hex),
            isLight: isLightColor(hex),
            lighten: adjustLightness(hex, 0.08),
            darken: adjustLightness(hex, -0.08),
        })),
    };
}

describe('brand colour parity fixture', () => {
    it('matches the committed fixture, or writes it under MYRA_WRITE_FIXTURES=1', () => {
        const payload = emit();

        if (process.env.MYRA_WRITE_FIXTURES === '1' || !existsSync(FIXTURE)) {
            mkdirSync(path.dirname(FIXTURE), { recursive: true });
            writeFileSync(FIXTURE, JSON.stringify(payload, null, 2) + '\n');
        }

        expect(JSON.parse(readFileSync(FIXTURE, 'utf8'))).toEqual(payload);
    });

    it('formats exactly three decimals for L and C and one for H', () => {
        expect(hexToOklch('#3b82f6')).toMatch(/^oklch\(\d\.\d{3} \d\.\d{3} \d+\.\d\)$/);
    });
});
