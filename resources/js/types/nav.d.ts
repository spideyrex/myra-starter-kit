/** Server-contributed navigation (the `myraNav` Inertia prop). */
export interface MyraNavItemPayload {
    labelKey: string;
    href: string | null;
    icon: string | null;
    permission: string | null;
    sort: number;
    activePrefix: string | null;
    items: MyraNavItemPayload[];
}

export interface MyraNavGroupPayload {
    labelKey: string;
    sort: number;
    items: MyraNavItemPayload[];
}

/** A hydrated sidebar item — the shape the layout's core groups already use. */
export interface NavItemVm {
    title: string;
    href: string | null;
    icon: any;
    permission: string | null;
    activePrefix?: string | null;
    items?: NavItemVm[];
}

export interface NavGroupVm {
    label: string;
    items: NavItemVm[];
}

/** A breadcrumb crumb as the nested-resource trait emits it. */
export interface ServerCrumb {
    labelKey?: string | null;
    label?: string | null;
    href?: string | null;
}
