import EmptyState from '@/components/EmptyState.vue';
import { definePlayground } from '@/composables/usePlayground';

interface EmptyStateValues {
    title: string;
    description: string;
}

export default definePlayground<EmptyStateValues>({
    key: 'emptyState',
    titleKey: 'gallery.playground.specs.emptyState',
    component: EmptyState,
    controls: [
        { name: 'title', labelKey: 'gallery.playground.control.title', kind: 'text', default: 'No results found', maxLength: 60 },
        {
            name: 'description',
            labelKey: 'gallery.playground.control.description',
            kind: 'text',
            default: 'Try a different filter or clear the search.',
            maxLength: 120,
        },
    ],
    snippet: (v, lang) => {
        if (lang === 'ts') {
            return [
                "import EmptyState from '@/components/EmptyState.vue';",
                '',
                `const empty = { title: ${JSON.stringify(v.title)}, description: ${JSON.stringify(v.description)} };`,
            ].join('\n');
        }

        if (lang === 'php') {
            return [
                'Table::make()',
                `    ->emptyStateHeading(${JSON.stringify(v.title)})`,
                `    ->emptyStateDescription(${JSON.stringify(v.description)});`,
            ].join('\n');
        }

        return [
            '<EmptyState',
            `    title="${v.title}"`,
            `    description="${v.description}"`,
            '/>',
        ].join('\n');
    },
});
