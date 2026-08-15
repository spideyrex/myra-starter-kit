import { describe, expect, it, beforeAll } from 'vitest';
import { Action, ActionGroup, EditAction, resolveActionItems } from '@/composables/useTableActions';

beforeAll(() => {
    (globalThis as any).route = (name: string, params?: any) => `/${name}/${params ?? ''}`;
});

const deny = () => false;
const allow = () => true;

describe('root ActionGroup permission gate', () => {
    it('drops the whole column when the root group permission is denied', () => {
        const group = ActionGroup.make([
            Action.make('Approve'),
            Action.make('Reject'),
        ]).permission('demo.manage').toSchema();

        const resolved = resolveActionItems([group], deny);

        expect(resolved.rootGroup).toBeNull();
        expect(resolved.items.length).toBe(0);
    });

    it('keeps the group and its items when the permission is granted', () => {
        const group = ActionGroup.make([Action.make('Approve')]).permission('demo.manage').toSchema();

        const resolved = resolveActionItems([group], allow);

        expect(resolved.rootGroup).not.toBeNull();
        expect(resolved.items.length).toBe(1);
    });

    it('still filters child items by their own permission', () => {
        const group = ActionGroup.make([
            Action.make('Approve').permission('demo.approve'),
            Action.make('Reject'),
        ]).toSchema();

        const resolved = resolveActionItems([group], (p: string) => p !== 'demo.approve');

        expect(resolved.rootGroup).not.toBeNull();
        expect(resolved.items.map((i: any) => i.label)).toEqual(['Reject']);
    });

    it('filters flat action lists too', () => {
        const items = [
            EditAction.make('admin.articles.edit').permission('articles.edit').toSchema(),
            Action.make('Preview').toSchema(),
        ];

        const resolved = resolveActionItems(items, (p: string) => p !== 'articles.edit');

        expect(resolved.rootGroup).toBeNull();
        expect(resolved.items.map((i: any) => i.label)).toEqual(['Preview']);
    });
});

describe('ActionGroup row-level visibility', () => {
    it('carries visibleWhen / hiddenWhen through to the schema', () => {
        const schema = ActionGroup.make([Action.make('Restore')])
            .visibleWhen((row: any) => !!row.deleted_at)
            .hiddenWhen((row: any) => row.locked === true)
            .toSchema();

        expect(schema.visibleFn!({ deleted_at: '2026-01-01' })).toBe(true);
        expect(schema.visibleFn!({ deleted_at: null })).toBe(false);
        expect(schema.hiddenFn!({ locked: true })).toBe(true);
    });
});
