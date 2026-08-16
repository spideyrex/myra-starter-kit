/**
 * The five-rule accessibility baseline every v2.6 page must clear.
 *
 * Deliberately structural: it reads the mounted DOM, so a page that regresses
 * an alt text or an unlabelled control fails without anyone remembering to
 * write a bespoke assertion for it.
 */
export type A11yRule = 'R1' | 'R2' | 'R3' | 'R4' | 'R5';

export interface A11yFinding {
    rule: A11yRule;
    detail: string;
}

function accessibleName(el: Element): string {
    const label = el.getAttribute('aria-label');
    if (label && label.trim() !== '') return label.trim();

    const labelledBy = el.getAttribute('aria-labelledby');
    if (labelledBy) {
        const named = labelledBy
            .split(/\s+/)
            .map(id => el.ownerDocument.getElementById(id)?.textContent ?? '')
            .join(' ')
            .trim();
        if (named !== '') return named;
    }

    const title = el.getAttribute('title');
    if (title && title.trim() !== '') return title.trim();

    return (el.textContent ?? '').trim();
}

export function auditA11y(root: Element): A11yFinding[] {
    const findings: A11yFinding[] = [];
    const doc = root.ownerDocument;

    // R1 — every image is either described or explicitly decorative.
    for (const img of Array.from(root.querySelectorAll('img'))) {
        const alt = img.getAttribute('alt');
        const decorative = alt === '' && (img.getAttribute('aria-hidden') === 'true' || alt === '');

        if (alt === null || (alt.trim() === '' && !decorative)) {
            findings.push({ rule: 'R1', detail: `img[src="${img.getAttribute('src') ?? ''}"] has no alt` });
        }
    }

    // R2 — every button has an accessible name.
    for (const button of Array.from(root.querySelectorAll('button, [role="button"]'))) {
        if (button.getAttribute('aria-hidden') === 'true') continue;

        if (accessibleName(button) === '') {
            findings.push({ rule: 'R2', detail: `${button.tagName.toLowerCase()} has no accessible name` });
        }
    }

    // R3 — no positive tabindex anywhere.
    for (const el of Array.from(root.querySelectorAll('[tabindex]'))) {
        if (Number(el.getAttribute('tabindex')) > 0) {
            findings.push({ rule: 'R3', detail: `${el.tagName.toLowerCase()} carries a positive tabindex` });
        }
    }

    // R4 — every form control is labelled.
    for (const field of Array.from(root.querySelectorAll('input, select, textarea'))) {
        const type = field.getAttribute('type');
        if (type === 'hidden') continue;

        const id = field.getAttribute('id');
        // jsdom has no CSS.escape, and an id may contain selector metacharacters.
        const labelled = id !== null
            && Array.from(doc.querySelectorAll('label[for]')).some(l => l.getAttribute('for') === id);

        if (labelled || accessibleName(field) !== '') continue;
        if (field.closest('label') !== null) continue;
        // A fieldset legend names a radio or checkbox group.
        if ((type === 'radio' || type === 'checkbox') && field.closest('fieldset')?.querySelector('legend')) continue;

        findings.push({ rule: 'R4', detail: `${field.tagName.toLowerCase()}[type=${type ?? 'text'}] has no label` });
    }

    // R5 — a clickable div or span must expose a role and be focusable.
    for (const el of Array.from(root.querySelectorAll('div[onclick], span[onclick]'))) {
        const role = el.getAttribute('role');
        const tabindex = el.getAttribute('tabindex');

        if (!role || tabindex === null) {
            findings.push({ rule: 'R5', detail: `${el.tagName.toLowerCase()} is clickable but not focusable` });
        }
    }

    return findings;
}

/** Compare findings against a committed waiver list; unwaived and stale both fail. */
export function reconcileWaivers(
    findings: A11yFinding[],
    waived: A11yRule[],
): { unwaived: A11yFinding[]; stale: A11yRule[] } {
    const hit = new Set(findings.map(f => f.rule));

    return {
        unwaived: findings.filter(f => !waived.includes(f.rule)),
        stale: waived.filter(rule => !hit.has(rule)),
    };
}
