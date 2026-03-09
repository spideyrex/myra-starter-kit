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
    currentTeam?: Team;
    teams?: Team[];
};

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
