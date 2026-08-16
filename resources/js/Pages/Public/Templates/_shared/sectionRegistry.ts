import type { Component } from 'vue';

/**
 * FILENAME IS THE SECTION TYPE KEY. Eager on purpose: the public front door
 * must paint in one pass, not waterfall a dynamic import behind the already
 * async template chunk. A package-contributed section only has to drop a
 * .vue file into ./sections to become renderable.
 */
const modules = import.meta.glob<{ default: Component }>('./sections/*.vue', { eager: true });

export const sectionComponents: Record<string, Component> = Object.fromEntries(
    Object.entries(modules).map(([path, module]) => [
        (path.split('/').pop() ?? '').replace(/\.vue$/, ''),
        module.default,
    ]),
);

export function hasSectionComponent(type: unknown): type is string {
    return typeof type === 'string' && Object.prototype.hasOwnProperty.call(sectionComponents, type);
}
