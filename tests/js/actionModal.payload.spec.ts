import { describe, expect, it, vi, beforeEach, beforeAll } from 'vitest';
import { mount } from '@vue/test-utils';
import ActionModal from '@/components/ActionModal.vue';
import { TextInput } from '@/composables/useFormSchema';

const state = vi.hoisted(() => ({ lastPost: null as any }));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (defaults: Record<string, any>) => {
        const form: any = { ...defaults, errors: {}, processing: false };
        let transform: ((d: Record<string, any>) => any) | null = null;
        form.transform = (fn: (d: Record<string, any>) => any) => {
            transform = fn;
            return form;
        };
        const send = (url: string) => {
            const data: Record<string, any> = {};
            for (const key of Object.keys(defaults)) data[key] = form[key];
            state.lastPost = { url, data: transform ? transform(data) : data };
        };
        form.post = send;
        form.put = send;
        form.patch = send;
        return form;
    },
    usePage: () => ({ props: {} }),
    Link: { name: 'Link', render: () => null },
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key }),
}));

const ModalStub = {
    name: 'Modal',
    props: ['open', 'title'],
    template: '<div><slot /><slot name="footer" /></div>',
};

beforeAll(() => {
    (globalThis as any).route = (name: string, params?: any) => `/${name}/${params?.id ?? params ?? ''}`;
});

beforeEach(() => {
    state.lastPost = null;
});

function submitWith(config: Record<string, any>) {
    const wrapper = mount(ActionModal, {
        props: { open: true, config: config as any },
        global: { stubs: { Modal: ModalStub } },
    });

    const button = wrapper.findAll('button').at(-1)!;
    return button.trigger('click').then(() => state.lastPost);
}

describe('ActionModal payload composition', () => {
    it('nests form values under payloadKey and lets them win over extraPayload', async () => {
        const posted = await submitWith({
            title: 'Duplicate',
            schema: [TextInput.make('title')],
            routeName: 'admin.articles.replicate',
            routeParams: { id: 7 },
            method: 'post',
            defaults: { title: 'Edited in the modal' },
            payloadKey: 'overrides',
            extraPayload: {
                except: ['slug'],
                relations: ['tags'],
                overrides: { title: 'From the row', status: 'draft' },
            },
        });

        expect(posted.data.except).toEqual(['slug']);
        expect(posted.data.relations).toEqual(['tags']);
        // Form value wins on collision; the untouched extra key survives.
        expect(posted.data.overrides).toEqual({ title: 'Edited in the modal', status: 'draft' });
    });

    it('merges at the root when no payloadKey is set', async () => {
        const posted = await submitWith({
            title: 'Archive',
            schema: [TextInput.make('reason')],
            routeName: 'admin.demo.archive-task',
            method: 'post',
            defaults: { reason: 'Superseded' },
            extraPayload: { reason: 'ignored', source: 'row' },
        });

        expect(posted.data).toEqual({ reason: 'Superseded', source: 'row' });
    });

    it('posts bare form values when neither key is set', async () => {
        const posted = await submitWith({
            title: 'Edit',
            schema: [TextInput.make('name')],
            routeName: 'admin.demo.update-task',
            method: 'post',
            defaults: { name: 'Task' },
        });

        expect(posted.data).toEqual({ name: 'Task' });
    });
});
