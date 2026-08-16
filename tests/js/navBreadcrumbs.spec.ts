import { describe, expect, it } from 'vitest';
import en from '@/i18n/locales/en.json';
import { hydrateCrumbs } from '@/nav/breadcrumbs';

const t = (key: string) => key.split('.').reduce((acc: any, part) => acc?.[part], en as any) ?? key;

/**
 * The exact shape ResolvesParentResource::parentBreadcrumbs() emits, asserted
 * against the server in tests/Feature/Resource/NestedResourceScopeBindingTest.
 */
const serverCrumbs = [
    { labelKey: 'clusters.learning.courses.label', label: null, href: '/admin/learning/courses' },
    { labelKey: null, label: 'Getting Started', href: null },
    { labelKey: 'clusters.learning.lessons.label', label: null, href: null },
];

describe('breadcrumb hydration', () => {
    it('translates the static segments and passes the record title through', () => {
        expect(hydrateCrumbs(serverCrumbs, t)).toEqual([
            { label: 'Courses', href: '/admin/learning/courses' },
            { label: 'Getting Started', href: undefined },
            { label: 'Lessons', href: undefined },
        ]);
    });

    it('never leaks a raw key', () => {
        for (const crumb of hydrateCrumbs(serverCrumbs, t)) {
            expect(crumb.label).not.toMatch(/^clusters\./);
        }
    });

    it('tolerates a missing prop', () => {
        expect(hydrateCrumbs(undefined, t)).toEqual([]);
    });
});
