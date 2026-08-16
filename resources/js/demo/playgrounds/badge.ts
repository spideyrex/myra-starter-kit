import { Badge } from '@/components/ui/badge';
import { definePlayground } from '@/composables/usePlayground';

interface BadgeValues {
    label: string;
    variant: string;
}

export default definePlayground<BadgeValues>({
    key: 'badge',
    titleKey: 'gallery.playground.specs.badge',
    component: Badge,
    slots: { default: 'label' },
    controls: [
        { name: 'label', labelKey: 'gallery.playground.control.label', kind: 'text', default: 'Active', maxLength: 24 },
        {
            name: 'variant',
            labelKey: 'gallery.playground.control.variant',
            kind: 'select',
            default: 'default',
            options: [
                { value: 'default', labelKey: 'gallery.playground.option.default' },
                { value: 'secondary', labelKey: 'gallery.playground.option.secondary' },
                { value: 'destructive', labelKey: 'gallery.playground.option.destructive' },
                { value: 'outline', labelKey: 'gallery.playground.option.outline' },
            ],
        },
    ],
    snippet: (v, lang) => {
        if (lang === 'ts') {
            return [
                "import { Badge } from '@/components/ui/badge';",
                '',
                `const props = { variant: ${JSON.stringify(v.variant)} };`,
            ].join('\n');
        }

        if (lang === 'php') {
            return [
                'TextColumn::make(\'status\')',
                '    ->badge()',
                `    ->color(${JSON.stringify(v.variant)});`,
            ].join('\n');
        }

        return `<Badge variant="${v.variant}">${v.label}</Badge>`;
    },
});
