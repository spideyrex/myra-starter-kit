import StatCard from '@/components/StatCard.vue';
import { definePlayground } from '@/composables/usePlayground';

interface StatCardValues {
    title: string;
    value: string;
    description: string;
    color: string;
}

/** Mounts the REAL StatCard, never a fork — the playground cannot drift. */
export default definePlayground<StatCardValues>({
    key: 'statCard',
    titleKey: 'gallery.playground.specs.statCard',
    component: StatCard,
    controls: [
        { name: 'title', labelKey: 'gallery.playground.control.title', kind: 'text', default: 'Total users', maxLength: 60 },
        { name: 'value', labelKey: 'gallery.playground.control.value', kind: 'text', default: '1,284', maxLength: 20 },
        { name: 'description', labelKey: 'gallery.playground.control.description', kind: 'text', default: 'Across every team', maxLength: 80 },
        {
            name: 'color',
            labelKey: 'gallery.playground.control.color',
            kind: 'select',
            default: 'blue',
            options: [
                { value: 'blue', labelKey: 'gallery.playground.option.blue' },
                { value: 'green', labelKey: 'gallery.playground.option.green' },
                { value: 'violet', labelKey: 'gallery.playground.option.violet' },
                { value: 'rose', labelKey: 'gallery.playground.option.rose' },
            ],
        },
    ],
    snippet: (v, lang) => {
        if (lang === 'ts') {
            return [
                "import StatCard from '@/components/StatCard.vue';",
                '',
                'const stat = {',
                `    title: ${JSON.stringify(v.title)},`,
                `    value: ${JSON.stringify(v.value)},`,
                `    description: ${JSON.stringify(v.description)},`,
                `    color: ${JSON.stringify(v.color)},`,
                '};',
            ].join('\n');
        }

        if (lang === 'php') {
            return [
                'StatWidget::make(\'users\')',
                `    ->label(${JSON.stringify(v.title)})`,
                `    ->value(${JSON.stringify(v.value)})`,
                `    ->description(${JSON.stringify(v.description)})`,
                `    ->color(${JSON.stringify(v.color)});`,
            ].join('\n');
        }

        return [
            '<StatCard',
            `    title="${v.title}"`,
            `    value="${v.value}"`,
            `    description="${v.description}"`,
            `    color="${v.color}"`,
            '/>',
        ].join('\n');
    },
});
