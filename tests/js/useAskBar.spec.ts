import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { useAskBar } from '@/composables/useAskBar';
import AiFilterChips from '@/components/admin/ai/AiFilterChips.vue';
import { testI18n } from './helpers/i18n';
// The tree the SERVER actually produced (written by AiFilterControllerTest).
import fixture from './fixtures/ai-filter-tree.json';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const post = axios.post as unknown as ReturnType<typeof vi.fn>;

beforeAll(() => {
    (globalThis as any).route = (name: string) => `/${name.replace(/\./g, '/')}`;
});

beforeEach(() => {
    post.mockReset();
});

function bar() {
    return useAskBar({ scope: () => fixture.scope, schema: () => fixture.constraints as any });
}

describe('useAskBar', () => {
    it('compiles through the ai route and never touches a filter route', async () => {
        post.mockResolvedValue({ data: { tree: fixture.tree, applied: false } });

        const ask = bar();
        ask.prompt.value = 'suspended accounts';
        await ask.compile();

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/admin/ai/filter');
        expect(post.mock.calls[0][1]).toEqual({ prompt: 'suspended accounts', scope: 'users' });
        expect(ask.state.value).toBe('review');
        expect(ask.draft.value).toEqual(fixture.tree);
    });

    it('refuses an empty prompt without a request', async () => {
        const ask = bar();
        await ask.compile();

        expect(post).not.toHaveBeenCalled();
        expect(ask.state.value).toBe('error');
        expect(ask.error.value).toBe('assistant.errors.promptLength');
    });

    it('surfaces a throttle as its own translatable key', async () => {
        post.mockRejectedValue({ response: { status: 429 } });

        const ask = bar();
        ask.prompt.value = 'suspended accounts';
        await ask.compile();

        expect(ask.error.value).toBe('assistant.errors.rateLimited');
        expect(ask.state.value).toBe('error');
    });

    it('drops the rule the path names and leaves its siblings alone', async () => {
        const nested = {
            conjunction: 'and',
            rules: [fixture.tree.rules[0], { field: 'name', operator: 'eq', value: 'Ford' }],
            groups: [{ conjunction: 'or', rules: [{ field: 'phone', operator: 'is_filled', value: null }], groups: [] }],
        };
        post.mockResolvedValue({ data: { tree: nested, applied: false } });

        const ask = bar();
        ask.prompt.value = 'anything';
        await ask.compile();

        ask.dropRule([0]);

        expect(ask.draft.value?.rules).toHaveLength(1);
        expect(ask.draft.value?.rules[0].field).toBe('name');
        expect(ask.draft.value?.groups[0].rules).toHaveLength(1);

        ask.dropRule([0, 0]);
        expect(ask.draft.value?.groups[0].rules).toHaveLength(0);
        expect(ask.draft.value?.rules).toHaveLength(1);
    });

    it('editRule patches only the addressed rule', async () => {
        post.mockResolvedValue({ data: { tree: fixture.tree, applied: false } });

        const ask = bar();
        ask.prompt.value = 'anything';
        await ask.compile();

        ask.editRule([0], { value: ['active'] });

        expect(ask.draft.value?.rules[0].value).toEqual(['active']);
        expect(ask.draft.value?.rules[0].field).toBe(fixture.tree.rules[0].field);
        // The imported fixture must not be mutated by an edit.
        expect(fixture.tree.rules[0].value).toEqual(['suspended']);
    });

    it('apply() returns the edited tree and discard() clears it', async () => {
        post.mockResolvedValue({ data: { tree: fixture.tree, applied: false } });

        const ask = bar();
        ask.prompt.value = 'anything';
        await ask.compile();
        ask.editRule([0], { value: ['pending'] });

        expect(ask.apply().rules[0].value).toEqual(['pending']);

        ask.discard();
        expect(ask.draft.value).toBeNull();
        expect(ask.state.value).toBe('idle');
        expect(post).toHaveBeenCalledTimes(1);
    });
});

describe('AiFilterChips', () => {
    function mountChips(tree: unknown, readonly = false) {
        return mount(AiFilterChips, {
            props: { tree: tree as any, schema: fixture.constraints as any, readonly },
            global: { plugins: [testI18n()] },
        });
    }

    it('renders one chip per rule in the real server tree', () => {
        const wrapper = mountChips(fixture.tree);

        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('is one of');
        expect(wrapper.text()).toContain('suspended');
    });

    it('emits the path of the chip whose remove button is pressed', async () => {
        const nested = {
            conjunction: 'and',
            rules: [fixture.tree.rules[0]],
            groups: [{ conjunction: 'or', rules: [{ field: 'name', operator: 'eq', value: 'Ford' }], groups: [] }],
        };
        const wrapper = mountChips(nested);
        const buttons = wrapper.findAll('button');

        expect(buttons).toHaveLength(2);
        await buttons[1].trigger('click');

        expect(wrapper.emitted('drop')?.[0][0]).toEqual([0, 0]);
    });

    it('renders no remove buttons when readonly', () => {
        expect(mountChips(fixture.tree, true).findAll('button')).toHaveLength(0);
    });

    it('never uses v-html for a value', () => {
        const wrapper = mountChips({
            conjunction: 'and',
            rules: [{ field: 'name', operator: 'eq', value: '<img src=x onerror=alert(1)>' }],
            groups: [],
        });

        expect(wrapper.html()).not.toContain('<img');
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    });
});
