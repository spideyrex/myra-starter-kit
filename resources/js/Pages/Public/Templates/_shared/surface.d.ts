/**
 * The page-surface payload, as HomepageController emits it.
 *
 * A structural copy held locally on purpose: the appearance engine ships in a
 * separate bundle and the public homepage must compile without it.
 */
export type PageSurfaceType = 'brand' | 'solid' | 'gradient' | 'pattern' | 'image' | 'none';

export type PageSurfaceScrim = 'none' | 'light' | 'medium' | 'strong';

export interface PageSurfacePayload {
    type: PageSurfaceType;
    recipe: string | null;
    scrim: PageSurfaceScrim;
    image_url: string | null;
    base: string;
    foreground: string;
    contrast: number;
    css_vars: Record<string, string>;
}
