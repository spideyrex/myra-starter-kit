import { Button } from '@/components/ui/button';
import { definePlayground } from '@/composables/usePlayground';

interface ButtonValues {
    label: string;
    variant: string;
    size: string;
    disabled: boolean;
}

export default definePlayground<ButtonValues>({
    key: 'button',
    titleKey: 'gallery.playground.specs.button',
    component: Button,
    slots: { default: 'label' },
    controls: [
        { name: 'label', labelKey: 'gallery.playground.control.label', kind: 'text', default: 'Save changes', maxLength: 40 },
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
                { value: 'ghost', labelKey: 'gallery.playground.option.ghost' },
                { value: 'link', labelKey: 'gallery.playground.option.link' },
            ],
        },
        {
            name: 'size',
            labelKey: 'gallery.playground.control.size',
            kind: 'select',
            default: 'default',
            options: [
                { value: 'sm', labelKey: 'gallery.playground.option.sm' },
                { value: 'default', labelKey: 'gallery.playground.option.default' },
                { value: 'lg', labelKey: 'gallery.playground.option.lg' },
            ],
        },
        { name: 'disabled', labelKey: 'gallery.playground.control.disabled', kind: 'boolean', default: false },
    ],
    snippet: (v, lang) => {
        if (lang === 'ts') {
            return [
                "import { Button } from '@/components/ui/button';",
                '',
                `const props = { variant: ${JSON.stringify(v.variant)}, size: ${JSON.stringify(v.size)}, disabled: ${v.disabled} };`,
            ].join('\n');
        }

        if (lang === 'php') {
            return [
                'Action::make(\'save\')',
                `    ->label(${JSON.stringify(v.label)})`,
                `    ->variant(${JSON.stringify(v.variant)})`,
                `    ->disabled(${v.disabled ? 'true' : 'false'});`,
            ].join('\n');
        }

        return [
            `<Button variant="${v.variant}" size="${v.size}"${v.disabled ? ' disabled' : ''}>`,
            `    ${v.label}`,
            '</Button>',
        ].join('\n');
    },
});
