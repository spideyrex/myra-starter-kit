import type { Component } from 'vue';
import { Pencil, Trash2, Eye } from 'lucide-vue-next';
import type { ActionSchema, BulkActionSchema } from '@/types/admin';

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

export class Action {
    protected _label: string;
    protected _icon?: Component;
    protected _color?: string;
    protected _urlFn?: (row: any) => string;
    protected _actionFn?: (row: any) => void;
    protected _requiresConfirmation = false;
    protected _confirmTitle?: string;
    protected _confirmDescription?: string;
    protected _permission?: string;
    protected _destructive = false;
    protected _hiddenFn?: (row: any) => boolean;
    protected _visibleFn?: (row: any) => boolean;
    protected _separator = false;

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): Action {
        return new Action(label);
    }

    icon(icon: Component): this {
        this._icon = icon;
        return this;
    }

    color(color: string): this {
        this._color = color;
        return this;
    }

    url(fn: (row: any) => string): this {
        this._urlFn = fn;
        return this;
    }

    action(fn: (row: any) => void): this {
        this._actionFn = fn;
        return this;
    }

    requiresConfirmation(title?: string, description?: string): this {
        this._requiresConfirmation = true;
        this._confirmTitle = title;
        this._confirmDescription = description;
        return this;
    }

    permission(perm: string): this {
        this._permission = perm;
        return this;
    }

    destructive(value = true): this {
        this._destructive = value;
        return this;
    }

    hidden(fn: (row: any) => boolean): this {
        this._hiddenFn = fn;
        return this;
    }

    visible(fn: (row: any) => boolean): this {
        this._visibleFn = fn;
        return this;
    }

    separator(value = true): this {
        this._separator = value;
        return this;
    }

    toSchema(): ActionSchema {
        return {
            label: this._label,
            icon: this._icon,
            color: this._color,
            urlFn: this._urlFn,
            actionFn: this._actionFn,
            requiresConfirmation: this._requiresConfirmation,
            confirmTitle: this._confirmTitle,
            confirmDescription: this._confirmDescription,
            permission: this._permission,
            destructive: this._destructive,
            hiddenFn: this._hiddenFn,
            visibleFn: this._visibleFn,
            separator: this._separator,
        };
    }
}

export class EditAction extends Action {
    private _routeName: string;

    constructor(routeName: string) {
        super('Edit');
        this._routeName = routeName;
        this._icon = Pencil;
        this._urlFn = (row: any) => route(this._routeName, row.id);
    }

    static make(routeName: string): EditAction {
        return new EditAction(routeName);
    }
}

export class ViewAction extends Action {
    private _routeName: string;

    constructor(routeName: string) {
        super('View');
        this._routeName = routeName;
        this._icon = Eye;
        this._urlFn = (row: any) => route(this._routeName, row.id);
    }

    static make(routeName: string): ViewAction {
        return new ViewAction(routeName);
    }
}

export class DeleteAction extends Action {
    private _routeName: string;
    private _confirmOpts: { title?: string; description?: string } = {};

    constructor(routeName: string) {
        super('Delete');
        this._routeName = routeName;
        this._icon = Trash2;
        this._destructive = true;
        this._separator = true;
        this._requiresConfirmation = true;
        this._confirmTitle = 'Delete';
        this._confirmDescription = 'Are you sure? This action cannot be undone.';
    }

    static make(routeName: string): DeleteAction {
        return new DeleteAction(routeName);
    }

    confirmTitle(title: string): this {
        this._confirmTitle = title;
        return this;
    }

    confirmDescription(description: string): this {
        this._confirmDescription = description;
        return this;
    }

    toSchema(): ActionSchema {
        return {
            ...super.toSchema(),
            deleteRouteName: this._routeName,
        };
    }
}

export class ActionGroup {
    private _actions: Action[];

    constructor(actions: Action[]) {
        this._actions = actions;
    }

    static make(actions: Action[]): ActionGroup {
        return new ActionGroup(actions);
    }

    toSchema(): ActionSchema[] {
        return this._actions.map(a => a.toSchema());
    }
}

export class BulkAction {
    private _label: string;
    private _actionFn?: (ids: number[]) => void;
    private _requiresConfirmation = false;
    private _confirmTitle?: string;
    private _confirmDescription?: string;
    private _deselectAfter = true;
    private _icon?: Component;
    private _permission?: string;
    private _destructive = false;

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): BulkAction {
        return new BulkAction(label);
    }

    action(fn: (ids: number[]) => void): this {
        this._actionFn = fn;
        return this;
    }

    requiresConfirmation(title?: string, description?: string): this {
        this._requiresConfirmation = true;
        this._confirmTitle = title;
        this._confirmDescription = description;
        return this;
    }

    deselectAfter(value = true): this {
        this._deselectAfter = value;
        return this;
    }

    icon(icon: Component): this {
        this._icon = icon;
        return this;
    }

    permission(perm: string): this {
        this._permission = perm;
        return this;
    }

    destructive(value = true): this {
        this._destructive = value;
        return this;
    }

    toSchema(): BulkActionSchema {
        return {
            label: this._label,
            actionFn: this._actionFn,
            requiresConfirmation: this._requiresConfirmation,
            confirmTitle: this._confirmTitle,
            confirmDescription: this._confirmDescription,
            deselectAfter: this._deselectAfter,
            icon: this._icon,
            permission: this._permission,
            destructive: this._destructive,
        };
    }
}
