import {
    Blocks, CircleHelp, CreditCard, Grid3x3, Image, Megaphone, Minus, Quote,
    Rocket, TrendingUp, Type,
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
