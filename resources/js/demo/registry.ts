import type { Component } from 'vue';
import {
    BarChart3,
    Bookmark,
    Building2,
    CheckSquare,
    Code,
    Eye,
    FormInput,
    Gauge,
    GripVertical,
    Info,
    Layers,
    LayoutDashboard,
    LayoutGrid,
    Link2,
    ListChecks,
    MapPin,
    PanelTop,
    Pencil,
    Puzzle,
    Repeat,
    Search,
    Send,
    SlidersHorizontal,
    Trash2,
    Type,
    Upload,
    Wand2,
} from 'lucide-vue-next';

/**
 * The client half of App\Admin\Demo\DemoEntry. Every string is an i18n key or
 * an identifier — the server never ships copy, so the same payload renders in
 * en, ms and zh without a round-trip.
 */
export interface DemoEntry {
    key: string;
    titleKey: string;
    descriptionKey: string;
    route: string;
    icon: string;
    badgeKey?: string | null;
    tags: string[];
    since: string;
    playground?: string | null;
}

/**
 * Icons cross the wire as strings, exactly as the sidebar's NAV_ICONS does.
 * This allowlist is the whole vocabulary: the server cannot name a component
 * the client did not ship, and an unknown name degrades to a placeholder.
 *
 * Lives in this module — not in the command registry — so the 27 icon imports
 * stay inside the lazily-loaded gallery chunk and never reach the main entry.
 */
export const DEMO_ICONS: Record<string, Component> = {
    BarChart3,
    Bookmark,
    Building2,
    CheckSquare,
    Code,
    Eye,
    FormInput,
    Gauge,
    GripVertical,
    Info,
    Layers,
    LayoutDashboard,
    LayoutGrid,
    Link2,
    ListChecks,
    MapPin,
    PanelTop,
    Pencil,
    Puzzle,
    Repeat,
    Search,
    Send,
    SlidersHorizontal,
    Trash2,
    Type,
    Upload,
    Wand2,
};

export function resolveDemoIcon(name: string): Component {
    // hasOwn, not a bare lookup: 'constructor' is truthy on any object literal.
    if (name && Object.prototype.hasOwnProperty.call(DEMO_ICONS, name)) {
        return DEMO_ICONS[name];
    }

    return LayoutGrid;
}
