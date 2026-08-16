import { describe, expect, it } from 'vitest';
import path from 'node:path';
import { scanBlocks } from '../../scripts/shadcn/a11y-scan.mjs';
import waivers from './fixtures/block-a11y-waivers.json';

/**
 * Upstream a11y debt is visible and BOUNDED, never silently swallowed:
 * an unwaived finding fails, and a waiver with nothing left to waive fails too,
 * so the list can only shrink.
 */
const findings = scanBlocks(path.resolve(__dirname, '../../resources/js/blocks')) as Record<string, string[]>;
const waived = waivers as Record<string, string[]>;

describe('vendored block accessibility baseline', () => {
    it('finds no unwaived rule break in any available block', () => {
        const unwaived: string[] = [];

        for (const [id, rules] of Object.entries(findings)) {
            for (const rule of rules) {
                if (!(waived[id] ?? []).includes(rule)) unwaived.push(`${id}: ${rule}`);
            }
        }

        expect(unwaived).toEqual([]);
    });

    it('keeps no waiver that has nothing left to waive', () => {
        const stale: string[] = [];

        for (const [id, rules] of Object.entries(waived)) {
            for (const rule of rules) {
                if (!(findings[id] ?? []).includes(rule)) stale.push(`${id}: ${rule}`);
            }
        }

        expect(stale).toEqual([]);
    });

    it('actually scanned the vendored tree', () => {
        expect(Object.keys(waived).length).toBeGreaterThan(0);
    });
});
