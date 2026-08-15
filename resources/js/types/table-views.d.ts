export interface TableViewPayload {
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
    filters?: Record<string, string>;
    dateRanges?: Record<string, { from: string; to: string }>;
    /** Opaque to the saved-views layer: shape/size-validated only, never semantically interpreted. */
    query?: Record<string, unknown>;
    columns?: Record<string, boolean>;
    columnOrder?: string[];
}

export interface SavedView {
    /** null for page-declared views. */
    id: number | null;
    slug: string | null;
    name: string;
    visibility: 'private' | 'team';
    is_default: boolean;
    owned: boolean;
    payload: TableViewPayload;
    /** Client-side gate only; declared views may carry one. */
    permission?: string;
    icon?: unknown;
}

export interface ColumnManagerOptions {
    /** 'user' is deferred — server-side column prefs are not shipped. */
    persist?: 'local' | 'none';
    reorderable?: boolean;
}

export interface ColumnManagerEntry {
    key: string;
    label: string;
    visible: boolean;
    pinned?: boolean;
    group?: string;
}
