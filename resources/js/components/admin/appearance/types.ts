// B's LOCAL structural copy of the v2.8 payload. Deliberately not imported from
// '@/types' — that file has exactly one owner this release.

export type BackgroundTypeKey = 'brand' | 'solid' | 'gradient' | 'pattern' | 'image' | 'none';
export type ScrimKey = 'none' | 'light' | 'medium' | 'strong';

export interface SurfacePayload {
    type: BackgroundTypeKey;
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
    contrast: number;
    warning: boolean;
}

export interface AuthLayoutSchema {
    key: string;
    component: string;
    titleKey: string;
    descriptionKey: string;
    thumbnail: string | null;
    flippable: boolean;
    supportsMedia: boolean;
    since: string;
}

export interface AppearanceOptions {
    types: BackgroundTypeKey[];
    gradients: string[];
    patterns: string[];
    scrims: ScrimKey[];
    recipeCss: Record<string, string>;
    recipeSize: Record<string, string>;
    minContrast: number;
}

export interface AppearanceSettingsPayload {
    auth_layout: string;
    auth_flip: boolean;
    auth_show_tagline: boolean;
    auth_bg_type: BackgroundTypeKey;
    auth_bg_color: string | null;
    auth_bg_recipe: string | null;
    auth_bg_image_path: string | null;
    auth_bg_scrim: ScrimKey;
    auth_bg_image_url: string | null;
    [key: string]: unknown;
}
