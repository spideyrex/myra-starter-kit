/**
 * Bundle D's LOCAL copy of the auth appearance payload.
 *
 * The canonical declarations live in resources/js/types/index.d.ts, which is
 * owned by the engine bundle. Importing them here would make three shells that
 * must always mount depend on a file another worktree writes, so the shape is
 * restated structurally instead. Re-pointing at '@/types' is post-merge tidying.
 */

export type SurfaceType = 'brand' | 'solid' | 'gradient' | 'pattern' | 'image' | 'none';

export type ScrimKey = 'none' | 'light' | 'medium' | 'strong';

export interface SurfacePayload {
    type: SurfaceType;
    recipe: string | null;
    scrim: ScrimKey;
    image_url: string | null;
    base: string;
    foreground: string;
    contrast: number;
    css_vars: Record<string, string>;
}

export interface AuthAppearancePayload {
    layout: string;
    component: string;
    flip: boolean;
    show_tagline: boolean;
    supports_media: boolean;
    surface: SurfacePayload;
}

const TYPES: SurfaceType[] = ['brand', 'solid', 'gradient', 'pattern', 'image', 'none'];
const SCRIMS: ScrimKey[] = ['none', 'light', 'medium', 'strong'];

/** The shape a shell mounts when nothing at all was shared. Today's look. */
export const STOCK_SURFACE: SurfacePayload = {
    type: 'brand',
    recipe: null,
    scrim: 'medium',
    image_url: null,
    base: '',
    foreground: '',
    contrast: 0,
    css_vars: {},
};

export const STOCK_AUTH: AuthAppearancePayload = {
    layout: 'split',
    component: 'SplitLayout',
    flip: false,
    show_tagline: true,
    supports_media: true,
    surface: STOCK_SURFACE,
};

function str(value: unknown, fallback: string): string {
    return typeof value === 'string' && value !== '' ? value : fallback;
}

function bool(value: unknown, fallback: boolean): boolean {
    return typeof value === 'boolean' ? value : fallback;
}

/** Only string values survive: the object is spread straight into :style. */
function cssVars(value: unknown): Record<string, string> {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return {};
    }

    const out: Record<string, string> = {};

    for (const [key, raw] of Object.entries(value as Record<string, unknown>)) {
        if (key.startsWith('--') && typeof raw === 'string') {
            out[key] = raw;
        }
    }

    return out;
}

/** Total. Half a payload, a null, an array or a hostile string all land on stock. */
export function normalizeSurface(raw: unknown): SurfacePayload {
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
        return STOCK_SURFACE;
    }

    const s = raw as Record<string, unknown>;
    const type = TYPES.includes(s.type as SurfaceType) ? (s.type as SurfaceType) : 'brand';

    return {
        type,
        recipe: typeof s.recipe === 'string' && s.recipe !== '' ? s.recipe : null,
        scrim: SCRIMS.includes(s.scrim as ScrimKey) ? (s.scrim as ScrimKey) : 'medium',
        image_url: typeof s.image_url === 'string' && s.image_url !== '' ? s.image_url : null,
        base: str(s.base, ''),
        foreground: str(s.foreground, ''),
        contrast: typeof s.contrast === 'number' && Number.isFinite(s.contrast) ? s.contrast : 0,
        css_vars: cssVars(s.css_vars),
    };
}

/**
 * The whole of what an authored appearance may put in `style`: the server's
 * already-sanitised custom properties, plus two references to them. No authored
 * string is ever interpolated, and no `url()` can be expressed here at all.
 */
export function surfaceStyle(surface: SurfacePayload): Record<string, string> {
    const plain = surface.type === 'brand' || surface.type === 'none';

    return {
        ...surface.css_vars,
        ...(plain
            ? {}
            : { backgroundColor: 'var(--myra-auth-bg)', color: 'var(--myra-auth-fg)' }),
    };
}

export function normalizeAuth(raw: unknown): AuthAppearancePayload {
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
        return STOCK_AUTH;
    }

    const a = raw as Record<string, unknown>;

    return {
        layout: str(a.layout, STOCK_AUTH.layout),
        component: str(a.component, STOCK_AUTH.component),
        flip: bool(a.flip, false),
        show_tagline: bool(a.show_tagline, true),
        supports_media: bool(a.supports_media, true),
        surface: normalizeSurface(a.surface),
    };
}
