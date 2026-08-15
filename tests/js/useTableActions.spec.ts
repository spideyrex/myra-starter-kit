import { describe, it, expect, beforeAll } from 'vitest';
import {
    Action,
    ActionDivider,
    ActionGroup,
    ActionSectionLabel,
    DeleteAction,
    ForceDeleteAction,
    ReplicateAction,
    RestoreAction,
    softDeleteActions,
    softDeleteBulkActions,
} from '@/composables/useTableActions';

beforeAll(() => {
    // Ziggy's global helper is not present outside the app shell.
    (globalThis as any).route = (name: string, params?: any) => `/${name}/${params ?? ''}`;
});

describe('soft-delete visibility defaults', () => {
    it('shows restore and force-delete only on trashed rows', () => {
        const restore = RestoreAction.make('admin.articles.restore').toSchema();
        const force = ForceDeleteAction.make('admin.articles.force-delete').toSchema();

        expect(restore.visibleFn!({ deleted_at: '2026-01-01' })).toBe(true);
        expect(restore.visibleFn!({ deleted_at: null })).toBe(false);
        expect(force.visibleFn!({ deleted_at: '2026-01-01' })).toBe(true);
        expect(force.visibleFn!({ deleted_at: null })).toBe(false);
    });

    it('shows delete only on active rows', () => {
        const del = DeleteAction.make('admin.articles.destroy').toSchema();

        expect(del.visibleFn!({ deleted_at: null })).toBe(true);
        expect(del.visibleFn!({ deleted_at: '2026-01-01' })).toBe(false);
    });

    it('keeps deleteRouteName for backwards compatibility', () => {
        expect(DeleteAction.make('admin.articles.destroy').toSchema().deleteRouteName)
            .toBe('admin.articles.destroy');
    });

    it('passes confirm copy through to the schema', () => {
        const schema = DeleteAction.make('admin.articles.destroy')
            .confirmTitle('Move to trash')
            .confirmDescription('You can restore it later.')
            .toSchema();

        expect(schema.requiresConfirmation).toBe(true);
        expect(schema.confirmTitle).toBe('Move to trash');
        expect(schema.confirmDescription).toBe('You can restore it later.');
    });

    it('does not clobber default confirm copy when requiresConfirmation() is called bare', () => {
        const schema = DeleteAction.make('admin.articles.destroy').requiresConfirmation().toSchema();

        expect(schema.confirmTitle).toBe('Delete');
    });
});

describe('ReplicateAction', () => {
    it('builds a payload from the fluent config', () => {
        const schema = ReplicateAction.make('admin.articles.replicate')
            .only(['title'])
            .withRelations(['tags'])
            .suffix('title')
            .overrides(() => ({ status: 'draft' }))
            .toSchema();

        const payload = schema.payloadFn!({ id: 1, title: 'X' });

        expect(payload.only).toEqual(['title']);
        expect(payload.relations).toEqual(['tags']);
        expect(payload.suffix).toEqual({ field: 'title', template: ':value (copy)' });
        expect(payload.overrides).toEqual({ status: 'draft' });
        expect(schema.method).toBe('post');
        expect(schema.requiresConfirmation).toBe(true);
    });

    it('switches to the modal path when a schema is supplied', () => {
        const schema = ReplicateAction.make('admin.articles.replicate')
            .schema([])
            .toSchema();

        expect(schema.requiresConfirmation).toBe(false);
        expect(schema.modalConfig?.routeName).toBe('admin.articles.replicate');
        expect(schema.modalConfig?.routeParamsFn!({ id: 7 })).toBe(7);
    });
});

describe('softDeleteActions', () => {
    it('returns four permission-gated actions on the conventional routes', () => {
        const [edit, del, restore, force] = softDeleteActions('admin.articles').map(a => a.toSchema());

        expect([edit.permission, del.permission, restore.permission, force.permission])
            .toEqual(['articles.edit', 'articles.delete', 'articles.edit', 'articles.delete']);
        expect(del.deleteRouteName).toBe('admin.articles.destroy');
        expect(restore.routeName).toBe('admin.articles.restore');
        expect(force.routeName).toBe('admin.articles.force-delete');
    });

    it('accepts a view action and route overrides', () => {
        const actions = softDeleteActions('admin.demo', {
            module: false,
            edit: false,
            deleteRoute: 'admin.demo.soft-delete',
        }).map(a => a.toSchema());

        expect(actions).toHaveLength(3);
        expect(actions.every(a => a.permission === undefined)).toBe(true);
        expect(actions[0].deleteRouteName).toBe('admin.demo.soft-delete');
    });

    it('builds restore and force-delete bulk actions', () => {
        const [restore, force] = softDeleteBulkActions('admin.articles').map(b => b.toSchema());

        expect(restore.permission).toBe('articles.edit');
        expect(force.permission).toBe('articles.delete');
        expect(force.destructive).toBe(true);
    });
});

describe('ActionGroup', () => {
    it('emits dividers and section labels as structural items', () => {
        const schema = ActionGroup.make([
            ActionSectionLabel.make('Danger zone'),
            ActionDivider.make(),
            Action.make('Do it'),
        ]).collapseAfter(3).width('lg').toSchema();

        expect(schema.kind).toBe('group');
        expect(schema.collapseAfter).toBe(3);
        expect(schema.width).toBe('lg');
        expect(schema.items.map(i => i.kind)).toEqual(['section', 'divider', 'action']);
    });

    it('nests groups', () => {
        const schema = ActionGroup.make([
            ActionGroup.make([Action.make('Child')]).label('Share'),
        ]).toSchema();

        expect(schema.items[0].kind).toBe('group');
        expect((schema.items[0] as any).label).toBe('Share');
    });
});

describe('Action generic route path', () => {
    it('records route, method, payload and success message', () => {
        const schema = Action.make('Archive')
            .route('admin.demo.archive-task', 'post')
            .payload(() => ({ reason: 'stale' }))
            .successMessage('Archived.')
            .external()
            .toSchema();

        expect(schema.routeName).toBe('admin.demo.archive-task');
        expect(schema.method).toBe('post');
        expect(schema.payloadFn!({})).toEqual({ reason: 'stale' });
        expect(schema.successMessage).toBe('Archived.');
        expect(schema.external).toBe(true);
    });
});
