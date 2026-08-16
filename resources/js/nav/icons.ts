import {
    Activity,
    BarChart3,
    Bell,
    BookOpen,
    ChevronRight,
    Database,
    FileText,
    FlaskConical,
    Folder,
    FolderOpen,
    GraduationCap,
    HeartPulse,
    Image,
    Key,
    LayoutDashboard,
    LayoutGrid,
    Mail,
    MailCheck,
    Newspaper,
    Settings,
    Shield,
    Smartphone,
    Users,
} from 'lucide-vue-next';

/**
 * Icons cross the wire as strings. This allowlist is the whole vocabulary — a
 * server (or a plugin) can never name a component the client did not already
 * ship, and an unknown name degrades to a placeholder instead of a blank slot.
 */
export const NAV_ICONS = {
    Activity,
    BarChart3,
    Bell,
    BookOpen,
    ChevronRight,
    Database,
    FileText,
    FlaskConical,
    Folder,
    FolderOpen,
    GraduationCap,
    HeartPulse,
    Image,
    Key,
    LayoutDashboard,
    LayoutGrid,
    Mail,
    MailCheck,
    Newspaper,
    Settings,
    Shield,
    Smartphone,
    Users,
} as const;

export type NavIconName = keyof typeof NAV_ICONS;

export function resolveNavIcon(name?: string | null) {
    // hasOwn, not a bare lookup: 'constructor' and 'toString' are truthy on any
    // object literal and would otherwise render as an icon.
    if (name && Object.prototype.hasOwnProperty.call(NAV_ICONS, name)) {
        return (NAV_ICONS as Record<string, any>)[name];
    }

    return NAV_ICONS.LayoutGrid;
}
