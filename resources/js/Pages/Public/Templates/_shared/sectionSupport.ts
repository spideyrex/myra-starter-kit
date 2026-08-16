/**
 * The five section keys a template declares support for.
 * Mirrors App\Homepage\HomepageTemplate::SECTIONS.
 */
export const LEGACY_SECTIONS: readonly string[] = ['hero', 'features', 'testimonials', 'pricing', 'cta'];

export const ALL_SECTIONS_SUPPORTED: string[] = [...LEGACY_SECTIONS];

/**
 * A template's `supports` list restricts ONLY the legacy five. A
 * package-contributed type is never hidden by a template declared before it
 * existed, which also keeps the "filter left zero blocks" edge bounded.
 */
export function isSupportedSection(type: unknown, supports: readonly string[]): boolean {
    if (typeof type !== 'string' || type === '') {
        return false;
    }

    return !LEGACY_SECTIONS.includes(type) || supports.includes(type);
}
