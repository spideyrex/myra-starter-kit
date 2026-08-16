import { beforeAll, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import fs from 'node:fs';
import path from 'node:path';
import type { DefineComponent } from 'vue';
import { exampleI18n, installExampleEnv } from './helpers/exampleEnv';
import waivers from './fixtures/example-a11y-waivers.json';
import manifest from '../../scripts/shadcn/examples-manifest.json';

const ROOT = path.resolve(__dirname, '../..');
const AUTHORED_ZERO_DEBT = ['mail', 'music', 'forms'];

const shells = import.meta.glob<DefineComponent>('@/examples/*/Page.vue', { eager: true });

function shellFor(id: string): DefineComponent {
    const key = Object.keys(shells).find(p => p.endsWith(`/examples/${id}/Page.vue`));

    if (!key) throw new Error(`No shell on disk for example [${id}]`);

    return shells[key].default ?? (shells[key] as unknown as DefineComponent);
}

/** Text content, aria-label, aria-labelledby, or an associated <label for>. */
function accessibleName(el: Element, root: ParentNode): string {
    const label = el.getAttribute('aria-label')?.trim();
    if (label) return label;

    const labelledBy = el.getAttribute('aria-labelledby');
    if (labelledBy) {
        const named = labelledBy
            .split(/\s+/)
            .map(id => root.querySelector(`#${CSS.escape(id)}`)?.textContent?.trim() ?? '')
            .join(' ')
            .trim();
        if (named) return named;
    }

    const id = el.getAttribute('id');
    if (id) {
        const associated = root.querySelector(`label[for="${CSS.escape(id)}"]`)?.textContent?.trim();
        if (associated) return associated;
    }

    const own = el.textContent?.trim() ?? '';
    if (own) return own;

    const title = el.getAttribute('title')?.trim();
    return title ?? '';
}

/** R1–R4 are DOM rules; R5 is a source rule, because a Vue @click leaves no attribute. */
function auditDom(root: HTMLElement): string[] {
    const broken: string[] = [];

    for (const img of Array.from(root.querySelectorAll('img'))) {
        const alt = img.getAttribute('alt');
        const decorative = alt === '' && img.getAttribute('aria-hidden') === 'true';

        if (!decorative && (alt === null || alt.trim() === '')) broken.push('R1');
    }

    for (const el of Array.from(root.querySelectorAll('button, [role="button"], [role="switch"], [role="checkbox"], [role="radio"]'))) {
        if (el.closest('[aria-hidden="true"]')) continue;
        if (accessibleName(el, root) === '') broken.push('R2');
    }

    for (const el of Array.from(root.querySelectorAll('[tabindex]'))) {
        if (Number(el.getAttribute('tabindex')) > 0) broken.push('R3');
    }

    for (const el of Array.from(root.querySelectorAll('input, select, textarea'))) {
        // reka-ui mirrors some controls into a hidden native input for form
        // submission; those are aria-hidden and carry no name by design.
        if (el.getAttribute('type') === 'hidden') continue;
        if (el.getAttribute('aria-hidden') === 'true' || el.closest('[aria-hidden="true"]')) continue;
        if (accessibleName(el, root) === '') broken.push('R4');
    }

    return [...new Set(broken)];
}

function auditSource(id: string): string[] {
    const dir = path.join(ROOT, 'resources/js/examples', id);
    const files: string[] = [];

    const walk = (d: string) => {
        for (const entry of fs.readdirSync(d, { withFileTypes: true })) {
            const full = path.join(d, entry.name);
            entry.isDirectory() ? walk(full) : full.endsWith('.vue') && files.push(full);
        }
    };
    walk(dir);

    for (const file of files) {
        const source = fs.readFileSync(file, 'utf8');

        for (const match of source.matchAll(/<(div|span)\b([^>]*)>/g)) {
            const attrs = match[2];
            if (!/@click|v-on:click/.test(attrs)) continue;
            if (/role\s*=/.test(attrs) && /tabindex\s*=\s*"0"/.test(attrs)) continue;

            return ['R5'];
        }
    }

    return [];
}

const available = Object.entries(manifest.examples)
    .filter(([, row]) => (row as { available: boolean }).available)
    .map(([id]) => id);

beforeAll(() => installExampleEnv());

describe('example accessibility baseline', () => {
    it('has at least one mountable example to audit', () => {
        expect(available.length).toBeGreaterThan(0);
    });

    for (const id of available) {
        it(`meets R1–R5 for the ${id} example`, () => {
            const wrapper = mount(shellFor(id), { global: { plugins: [exampleI18n()] } });

            const failures = [...auditDom(wrapper.element as HTMLElement), ...auditSource(id)].sort();
            const waived = ((waivers as Record<string, string[]>)[id] ?? []).slice().sort();

            const unwaived = failures.filter(rule => !waived.includes(rule));
            const stale = waived.filter(rule => !failures.includes(rule));

            expect(unwaived, `${id} breaks ${unwaived.join(', ')} with no waiver`).toEqual([]);
            expect(stale, `${id} waives ${stale.join(', ')} but no longer breaks it — delete the waiver`).toEqual([]);

            wrapper.unmount();
        });
    }

    it('permits no waiver at all for the three authored applications', () => {
        for (const id of AUTHORED_ZERO_DEBT) {
            expect(
                (waivers as Record<string, string[]>)[id],
                `${id} is authored here, so its accessibility debt is ours and must be zero`,
            ).toBeUndefined();
        }
    });

    it('audits the authored applications, not just the vendored ones', () => {
        for (const id of AUTHORED_ZERO_DEBT) {
            expect(available).toContain(id);
        }
    });
});
