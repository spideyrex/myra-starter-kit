export interface Team {
    id: number;
    name: string;
    slug: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    phone?: string;
    avatar?: string;
    status: 'active' | 'suspended' | 'pending';
    roles: string[];
    permissions: string[];
    deleted_at?: string;
    created_at: string;
    updated_at: string;
    current_team_id?: number;
    current_team?: Team;
    teams?: Team[];
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    group?: string;
    created_at: string;
    updated_at: string;
}

export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
}

// >>> MYRA v2.4 [D] START
/**
 * A cursor page. `meta.mode` is present ONLY here — an absent `mode` means
 * length-aware, so every existing payload keeps its exact shape.
 */
export interface CursorPaginatedData<T> {
    data: T[];
    links: {
        first: null;
        last: null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        mode: 'cursor';
        path: string;
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
}

export type TableData<T> = PaginatedData<T> | CursorPaginatedData<T>;
// <<< MYRA v2.4 [D] END

export interface Notification {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

export interface SiteSettings {
    site_name: string;
    site_description: string;
    site_logo?: string;
    maintenance_mode: boolean;
    logo_url?: string;
    favicon_url?: string;
    primary_color?: string;
    theme?: string;
    logo_position?: string;
    sidebar_background?: string | null;
    sidebar_foreground?: string | null;
    sidebar_accent?: string | null;
}

export interface FirebaseConfig {
    apiKey: string;
    authDomain: string;
    projectId: string;
    storageBucket: string;
    messagingSenderId: string;
    appId: string;
    vapidKey?: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    unreadNotificationsCount?: number;
    impersonating?: boolean;
    impersonatorName?: string;
    siteSettings?: SiteSettings;
    firebaseConfig?: FirebaseConfig | null;
    aiEnabled?: boolean;
    currentTeam?: Team;
    teams?: Team[];
};

export interface HomepageFeature {
    icon: string;
    title: string;
    description: string;
}

export interface HomepageTestimonial {
    name: string;
    role: string;
    quote: string;
}

export interface HomepagePricingPlan {
    name: string;
    price: string;
    period: string;
    features: string;
    cta_text: string;
    cta_url: string;
    highlighted: boolean;
}

export interface HomepageLink {
    label: string;
    url: string;
}

export interface HomepageData {
    enabled: boolean;
    hero_title: string;
    hero_subtitle: string;
    hero_cta_text: string;
    hero_cta_url: string;
    hero_image_path: string | null;
    hero_image_url: string | null;

    features_enabled: boolean;
    features_title: string;
    features_subtitle: string;
    features: HomepageFeature[];

    testimonials_enabled: boolean;
    testimonials_title: string;
    testimonials_subtitle: string;
    testimonials: HomepageTestimonial[];

    pricing_enabled: boolean;
    pricing_title: string;
    pricing_subtitle: string;
    pricing_plans: HomepagePricingPlan[];

    cta_enabled: boolean;
    cta_title: string;
    cta_subtitle: string;
    cta_button_text: string;
    cta_button_url: string;

    footer_text: string;
    footer_links: HomepageLink[];

    navbar_cta_text: string;
    navbar_cta_url: string;
    navbar_links: HomepageLink[];
}

export interface CategoryData {
    id: number;
    name: string;
    slug: string;
    description?: string;
    articles_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface PageData {
    id: number;
    title: string;
    slug: string;
    body_html: string;
    excerpt?: string;
    meta?: {
        meta_title?: string;
        meta_description?: string;
        meta_keywords?: string;
    };
    status: 'draft' | 'published' | 'archived';
    is_public: boolean;
    published_at?: string;
    featured_image_url?: string | null;
    creator?: { id: number; name: string };
    deleted_at?: string;
    created_at?: string;
    updated_at?: string;
}

export interface ArticleData extends PageData {
    tags?: string[];
    category_id?: number;
    category?: { id: number; name: string; slug: string };
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: string;
    permission?: string;
    children?: NavItem[];
}

// >>> MYRA v2.6 [A] START
export interface BlockSchema {
    key: string;
    titleKey: string;
    descriptionKey: string;
    category: string;
    entryFile: string;
    available: boolean;
    unavailableReason: string | null;
    registryDependencies: string[];
    npmDependencies: string[];
    tags: string[];
    since: string;
    viewport: string;
}

export interface BlockSourceFile {
    path: string;
    language: string;
    content: string;
}
// <<< MYRA v2.6 [A] END

// >>> MYRA v2.6 [B] START
export interface ExampleEntry {
    key: string;
    titleKey: string;
    descriptionKey: string;
    shell: string;
    origin: 'fetched' | 'authored';
    sourceRef: string | null;
    available: boolean;
    unavailableReason: string | null;
    registryDependencies: string[];
    npmDependencies: string[];
    dataFiles: string[];
    surfaces: string[];
    tags: string[];
    since: string;
    fileCount: number;
}

export interface ExampleSourceFile {
    path: string;
    language: string;
    content: string;
}
// <<< MYRA v2.6 [B] END
// >>> MYRA v2.6 [C] START
export interface BrandPaletteTokens {
    primary: string;
    accent: string | null;
    sidebar_background: string | null;
    sidebar_foreground: string | null;
    sidebar_accent: string | null;
    preset: string;
}

export interface BrandTokens {
    enabled: boolean;
    name: string;
    short_name: string;
    tagline: string;
    description: string;
    logo_url: string | null;
    logo_dark_url: string | null;
    mark_url: string | null;
    favicon_url: string | null;
    og_image_url: string | null;
    logo_position: string;
    initial: string;
    palette: BrandPaletteTokens;
    typography: { sans: string; mono: string };
    radius: string;
    dark_default: boolean;
    hash: string;
}

export interface BrandContrast {
    ratio: number;
    foreground: string;
}
// <<< MYRA v2.6 [C] END
// >>> MYRA v2.6 [D] START
/** The client half of App\Homepage\HomepageTemplate. Strings are i18n keys. */
export interface TemplateSchema {
    key: string;
    component: string;
    titleKey: string;
    descriptionKey: string;
    thumbnail: string | null;
    supports: string[];
    since: string;
}
// <<< MYRA v2.6 [D] END
// >>> MYRA v2.7 [A] START
export interface PageSectionRow {
    id: string;
    type: string;
    enabled?: boolean;
    variant: Record<string, unknown>;
    data: Record<string, unknown>;
}

/** The client half of App\Homepage\Sections\SectionField. */
export interface PageSectionFieldSchema {
    name: string;
    type: string;
    labelKey: string;
    required: boolean;
    default: unknown;
    max: number | null;
    options?: string[];
    of?: PageSectionFieldSchema[];
}

/** The client half of App\Homepage\Sections\SectionType. */
export interface PageSectionSchema {
    key: string;
    labelKey: string;
    descriptionKey: string;
    icon: string;
    group: 'content' | 'proof' | 'conversion' | 'layout';
    titleField: string | null;
    maxPerPage: number;
    variants: Record<string, string[] | 'bool'>;
    fields: PageSectionFieldSchema[];
}
// <<< MYRA v2.7 [A] END
