import {
    Blocks, CircleHelp, CreditCard, Grid3x3, Image, Megaphone, Minus, Quote,
    Rocket, TrendingUp, Type,
    BarChart3, Bell, Cloud, Code, Database, Globe, Heart, Layers, Layout, Lock,
    Mail, Search, Settings, Shield, Star, Users, Zap,
} from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * The ten core section types' lucide icons, static-imported so the admin chunk
 * never pulls the whole lucide set. A package-contributed type falls back to the
 * generic mark rather than rendering nothing.
 */
const ICONS: Record<string, Component> = {
    Rocket, Grid3x3, Quote, CreditCard, Megaphone,
    Type, Image, TrendingUp, CircleHelp, Minus,
};

export const FALLBACK_SECTION_ICON: Component = Blocks;

export function sectionIcon(name: string | undefined | null): Component {
    return (name && ICONS[name]) || FALLBACK_SECTION_ICON;
}

/**
 * The allowlist an `icon` FIELD may hold. It must stay byte-identical to
 * SectionField::ICON_ALLOWLIST and to FeatureGrid.vue's iconMap: the server
 * coerces anything outside it to '', so offering a name that is not here would
 * accept an edit the save then silently drops.
 */
const FIELD_ICONS: Record<string, Component> = {
    Zap, Shield, BarChart3, Users, Lock, Globe, Rocket, Heart, Star,
    Code, Database, Cloud, Settings, Mail, Bell, Search, Layers, Layout,
};

export const ICON_FIELD_NAMES: string[] = Object.keys(FIELD_ICONS);

export function fieldIcon(name: string | undefined | null): Component | null {
    return (name && FIELD_ICONS[name]) || null;
}
