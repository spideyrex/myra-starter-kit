import type { Component } from 'vue';
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2, Eye, Copy, RotateCcw, MoreHorizontal } from 'lucide-vue-next';
import type { ActionSchema, ActionGroupSchema, BulkActionSchema, SemanticColor } from '@/types/admin';
import type { SchemaItem } from '@/composables/useFormSchema';

export type ActionMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface ModalConfig {
    schema: SchemaItem[];
    routeName: string;
    method?: 'post' | 'put' | 'patch';
    defaultsFn?: (row: any) => Record<string, any>;
    /** Route params for the submit URL. Defaults to `{ id: row.id }`. */
    routeParamsFn?: (row: any) => any;
    submitLabel?: string;
    /** Nests the form values under this key instead of merging them at the root. */
    payloadKey?: string;
    /** Non-form data merged into the request alongside the form values. */
    extraPayloadFn?: (row: any) => Record<string, any>;
}

export class Action {
    protected _label: string;
    protected _icon?: Component;
    protected _color?: SemanticColor | string;
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
    protected _modalConfig?: ModalConfig;
    protected _routeName?: string;
    protected _method?: ActionMethod;
    protected _routeParamsFn?: (row: any) => any;
    protected _payloadFn?: (row: any) => Record<string, any>;
    protected _successMessage?: string;
    protected _badgeFn?: (row: any) => string | number | null;
    protected _external = false;
    protected _tooltip?: string;

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): Action {
        return new Action(label);
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    icon(icon: Component): this {
        this._icon = icon;
        return this;
    }

    color(color: SemanticColor | string): this {
        this._color = color;
        return this;
    }

    url(fn: (row: any) => string): this {
        this._urlFn = fn;
        return this;
    }

    /** Opens the URL in a new tab instead of the Inertia router. */
    external(value = true): this {
        this._external = value;
        return this;
    }

    action(fn: (row: any) => void): this {
        this._actionFn = fn;
        return this;
    }

    modal(config: ModalConfig): this {
        this._modalConfig = config;
        return this;
    }

    /** Framework-owned request path: DataTable issues it and toasts on success. */
    route(routeName: string, method: ActionMethod = 'post'): this {
        this._routeName = routeName;
        this._method = method;
        return this;
    }

    routeParams(fn: (row: any) => any): this {
        this._routeParamsFn = fn;
        return this;
    }

    payload(fn: (row: any) => Record<string, any>): this {
        this._payloadFn = fn;
        return this;
    }

    successMessage(message: string): this {
        this._successMessage = message;
        return this;
    }

    badge(fn: (row: any) => string | number | null): this {
        this._badgeFn = fn;
        return this;
    }

    tooltip(text: string): this {
        this._tooltip = text;
        return this;
    }

    requiresConfirmation(title?: string, description?: string): this {
        this._requiresConfirmation = true;
        if (title !== undefined) this._confirmTitle = title;
        if (description !== undefined) this._confirmDescription = description;
        return this;
    }

    confirmTitle(title: string): this {
        this._confirmTitle = title;
        return this;
    }

    confirmDescription(description: string): this {
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
            kind: 'action',
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
            external: this._external,
            tooltip: this._tooltip,
            badgeFn: this._badgeFn,
            routeName: this._routeName,
            method: this._method,
            routeParamsFn: this._routeParamsFn,
            payloadFn: this._payloadFn,
            successMessage: this._successMessage,
            modalConfig: this._modalConfig,
        };
    }
}

export class EditAction extends Action {
    constructor(routeName: string) {
        super('Edit');
        this._routeName = routeName;
        this._icon = Pencil;
        this._urlFn = (row: any) => route(routeName, row.id);
    }

    static make(routeName: string): EditAction {
        return new EditAction(routeName);
    }
}

export class ViewAction extends Action {
    constructor(routeName: string) {
        super('View');
        this._routeName = routeName;
        this._icon = Eye;
        this._urlFn = (row: any) => route(routeName, row.id);
    }

    static make(routeName: string): ViewAction {
        return new ViewAction(routeName);
    }
}

export class DeleteAction extends Action {
    constructor(routeName: string) {
        super('Delete');
        this._routeName = routeName;
        this._method = 'delete';
        this._icon = Trash2;
        this._destructive = true;
        this._separator = true;
        this._requiresConfirmation = true;
        this._confirmTitle = 'Delete';
        this._confirmDescription = 'Are you sure? This action cannot be undone.';
        this._visibleFn = (row: any) => !row.deleted_at;
    }

    static make(routeName: string): DeleteAction {
        return new DeleteAction(routeName);
    }

    toSchema(): ActionSchema {
        return {
            ...super.toSchema(),
            // BC: pages/tests still read deleteRouteName.
            deleteRouteName: this._routeName,
        };
    }
}

export class RestoreAction extends Action {
    constructor(routeName: string) {
        super('Restore');
        this._routeName = routeName;
        this._method = 'post';
        this._icon = RotateCcw;
        this._requiresConfirmation = true;
        this._confirmTitle = 'Restore record';
        this._confirmDescription = 'This record will be returned to the active list.';
        this._visibleFn = (row: any) => !!row.deleted_at;
        this._successMessage = 'Restored successfully.';
    }

    static make(routeName: string): RestoreAction {
        return new RestoreAction(routeName);
    }
}

export class ForceDeleteAction extends Action {
    constructor(routeName: string) {
        super('Delete permanently');
        this._routeName = routeName;
        this._method = 'delete';
        this._icon = Trash2;
        this._destructive = true;
        this._separator = true;
        this._requiresConfirmation = true;
        this._confirmTitle = 'Delete permanently';
        this._confirmDescription = 'This cannot be undone. The record and its files are destroyed.';
        this._visibleFn = (row: any) => !!row.deleted_at;
        this._successMessage = 'Permanently deleted.';
    }

    static make(routeName: string): ForceDeleteAction {
        return new ForceDeleteAction(routeName);
    }
}

export class ReplicateAction extends Action {
    private _except: string[] = [];
    private _only?: string[];
    private _relations: string[] = [];
    private _overridesFn?: (row: any) => Record<string, any>;
    private _suffixField?: string;
    private _suffixTemplate = ':value (copy)';
    private _schema?: SchemaItem[];
    private _redirectTo?: string;
    private _fillFromFn?: (row: any) => Record<string, any>;

    constructor(routeName: string) {
        super('Duplicate');
        this._routeName = routeName;
        this._method = 'post';
        this._icon = Copy;
        this._requiresConfirmation = true;
        this._confirmTitle = 'Duplicate';
        this._confirmDescription = 'A copy of this record will be created.';
        this._successMessage = 'Record duplicated.';
    }

    static make(routeName: string): ReplicateAction {
        return new ReplicateAction(routeName);
    }

    /** Deny-list: these columns are not copied. */
    except(cols: string[]): this {
        this._except = cols;
        return this;
    }

    /** Allow-list: only these columns are copied. Wins over except(). */
    only(cols: string[]): this {
        this._only = cols;
        return this;
    }

    /** One level deep. Names are re-whitelisted server-side. */
    withRelations(names: string[]): this {
        this._relations = names;
        return this;
    }

    overrides(fn: (row: any) => Record<string, any>): this {
        this._overridesFn = fn;
        return this;
    }

    suffix(field: string, template = ':value (copy)'): this {
        this._suffixField = field;
        this._suffixTemplate = template;
        return this;
    }

    /** Edit before saving — rides the existing ModalConfig/ActionModal path. */
    schema(items: SchemaItem[]): this {
        this._schema = items;
        return this;
    }

    /** Modal defaults derived from the row. Distinct from overrides(), which is the payload. */
    fillFrom(fn: (row: any) => Record<string, any>): this {
        this._fillFromFn = fn;
        return this;
    }

    redirectTo(routeName: string): this {
        this._redirectTo = routeName;
        return this;
    }

    private replicatePayload(row: any): Record<string, any> {
        return {
            except: this._except,
            only: this._only ?? null,
            relations: this._relations,
            suffix: this._suffixField
                ? { field: this._suffixField, template: this._suffixTemplate }
                : null,
            overrides: this._overridesFn?.(row) ?? {},
            redirect_to: this._redirectTo ?? null,
        };
    }

    toSchema(): ActionSchema {
        if (this._schema) {
            return {
                ...super.toSchema(),
                requiresConfirmation: false,
                modalConfig: {
                    schema: this._schema,
                    routeName: this._routeName!,
                    method: 'post',
                    submitLabel: this._label,
                    routeParamsFn: (row: any) => row.id,
                    defaultsFn: (row: any) => ({
                        ...row,
                        ...(this._fillFromFn?.(row) ?? this._overridesFn?.(row) ?? {}),
                    }),
                    // Without these the modal posts bare form values and the
                    // except/only/relations/suffix config never reaches the server.
                    payloadKey: 'overrides',
                    extraPayloadFn: (row: any) => this.replicatePayload(row),
                },
            };
        }

        return {
            ...super.toSchema(),
            routeParamsFn: this._routeParamsFn ?? ((row: any) => row.id),
            payloadFn: (row: any) => this.replicatePayload(row),
        };
    }
}

// --- Grouping primitives ---

export class ActionDivider {
    static make(): ActionDivider {
        return new ActionDivider();
    }

    toSchema(): ActionSchema {
        return { kind: 'divider', label: '', requiresConfirmation: false, destructive: false, separator: false };
    }
}

export class ActionSectionLabel {
    constructor(private readonly _text: string) {}

    static make(text: string): ActionSectionLabel {
        return new ActionSectionLabel(text);
    }

    toSchema(): ActionSchema {
        return { kind: 'section', label: this._text, requiresConfirmation: false, destructive: false, separator: false };
    }
}

export type ActionItem = Action | ActionGroup | ActionDivider | ActionSectionLabel;

export class ActionGroup {
    private _items: ActionItem[];
    private _label = 'Actions';
    private _icon: Component = MoreHorizontal;
    private _color?: SemanticColor | string;
    private _size: 'sm' | 'default' | 'lg' = 'sm';
    private _asButton = false;
    private _buttonGroup = false;
    private _tooltip?: string;
    private _badgeFn?: (row: any) => string | number | null;
    private _placement: 'bottom-start' | 'bottom-end' | 'top-start' | 'top-end' = 'bottom-end';
    private _width: 'sm' | 'md' | 'lg' = 'md';
    private _maxHeight = '20rem';
    private _collapseAfter?: number;
    private _permission?: string;
    private _visibleFn?: (row: any) => boolean;
    private _hiddenFn?: (row: any) => boolean;

    constructor(items: ActionItem[]) {
        this._items = items;
    }

    static make(items: ActionItem[]): ActionGroup {
        return new ActionGroup(items);
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    icon(icon: Component): this {
        this._icon = icon;
        return this;
    }

    color(color: SemanticColor | string): this {
        this._color = color;
        return this;
    }

    size(size: 'sm' | 'default' | 'lg'): this {
        this._size = size;
        return this;
    }

    /** Render the trigger as a labelled button instead of an icon button. */
    button(value = true): this {
        this._asButton = value;
        return this;
    }

    /** Render every item inline as a joined button group — no dropdown. */
    buttonGroup(value = true): this {
        this._buttonGroup = value;
        return this;
    }

    tooltip(text: string): this {
        this._tooltip = text;
        return this;
    }

    badge(fn: (row: any) => string | number | null): this {
        this._badgeFn = fn;
        return this;
    }

    placement(p: 'bottom-start' | 'bottom-end' | 'top-start' | 'top-end'): this {
        this._placement = p;
        return this;
    }

    width(w: 'sm' | 'md' | 'lg'): this {
        this._width = w;
        return this;
    }

    maxHeight(css: string): this {
        this._maxHeight = css;
        return this;
    }

    /** Below n visible items render inline; at or above, collapse to a dropdown. */
    collapseAfter(n: number): this {
        this._collapseAfter = n;
        return this;
    }

    permission(perm: string): this {
        this._permission = perm;
        return this;
    }

    visibleWhen(fn: (row: any) => boolean): this {
        this._visibleFn = fn;
        return this;
    }

    hiddenWhen(fn: (row: any) => boolean): this {
        this._hiddenFn = fn;
        return this;
    }

    toSchema(): ActionGroupSchema {
        return {
            kind: 'group',
            label: this._label,
            icon: this._icon,
            color: this._color,
            size: this._size,
            asButton: this._asButton,
            buttonGroup: this._buttonGroup,
            tooltip: this._tooltip,
            badgeFn: this._badgeFn,
            placement: this._placement,
            width: this._width,
            maxHeight: this._maxHeight,
            collapseAfter: this._collapseAfter,
            permission: this._permission,
            visibleFn: this._visibleFn,
            hiddenFn: this._hiddenFn,
            items: this._items.map(i => i.toSchema()),
        };
    }
}

/**
 * A single top-level ActionGroup configures the trigger itself rather than
 * nesting a submenu. Its `permission()` gates the whole column: without this the
 * group renders and only its children are filtered, so a denied group still
 * paints an empty trigger.
 */
export function resolveActionItems(
    items: Array<ActionSchema | ActionGroupSchema>,
    can: (permission: string) => boolean,
): { rootGroup: ActionGroupSchema | null; items: Array<ActionSchema | ActionGroupSchema> } {
    const single = items.length === 1 && (items[0] as ActionGroupSchema).kind === 'group'
        ? (items[0] as ActionGroupSchema)
        : null;

    if (single?.permission && !can(single.permission)) {
        return { rootGroup: null, items: [] };
    }

    const source = single?.items ?? items;

    return {
        rootGroup: single,
        items: source.filter(i => !(i as any).permission || can((i as any).permission)),
    };
}

// --- Soft-delete presets ---

export interface SoftDeleteActionOptions {
    /** Shield module slug. `false` attaches no permissions (demo pages). */
    module?: string | false;
    view?: boolean;
    edit?: boolean;
    viewRoute?: string;
    editRoute?: string;
    deleteRoute?: string;
    restoreRoute?: string;
    forceDeleteRoute?: string;
}

/** `softDeleteActions('admin.articles')` → View?/Edit/Delete/Restore/Delete permanently. */
export function softDeleteActions(prefix: string, opts: SoftDeleteActionOptions = {}): Action[] {
    const module = opts.module === false ? null : (opts.module ?? prefix.split('.').pop()!);
    const perm = (ability: string) => (module ? `${module}.${ability}` : undefined);
    const withPerm = <T extends Action>(action: T, ability: string): T => {
        const p = perm(ability);
        return p ? (action.permission(p) as T) : action;
    };

    const actions: Action[] = [];
    const active = (row: any) => !row.deleted_at;

    if (opts.view) {
        actions.push(withPerm(ViewAction.make(opts.viewRoute ?? `${prefix}.show`).visible(active), 'view'));
    }
    if (opts.edit !== false) {
        actions.push(withPerm(EditAction.make(opts.editRoute ?? `${prefix}.edit`).visible(active), 'edit'));
    }

    actions.push(
        withPerm(DeleteAction.make(opts.deleteRoute ?? `${prefix}.destroy`), 'delete'),
        withPerm(RestoreAction.make(opts.restoreRoute ?? `${prefix}.restore`), 'edit'),
        withPerm(ForceDeleteAction.make(opts.forceDeleteRoute ?? `${prefix}.force-delete`), 'delete'),
    );

    return actions;
}

/** Restore + Delete permanently, POSTing `{ ids, action }` to `${prefix}.bulk-action`. */
export function softDeleteBulkActions(
    prefix: string,
    module?: string | false,
    post?: (routeName: string, payload: Record<string, any>) => void,
): BulkAction[] {
    const mod = module === false ? null : (module ?? prefix.split('.').pop()!);
    const send = post ?? ((routeName: string, payload: Record<string, any>) => {
        router.post(route(routeName), payload);
    });

    const restore = BulkAction.make('Restore')
        .icon(RotateCcw)
        .requiresConfirmation('Restore records', 'The selected records will return to the active list.')
        .action((ids: number[]) => send(`${prefix}.bulk-action`, { ids, action: 'restore' }));

    const force = BulkAction.make('Delete permanently')
        .icon(Trash2)
        .destructive()
        .requiresConfirmation('Delete permanently', 'This cannot be undone. The selected records are destroyed.')
        .action((ids: number[]) => send(`${prefix}.bulk-action`, { ids, action: 'force_delete' }));

    return mod
        ? [restore.permission(`${mod}.edit`), force.permission(`${mod}.delete`)]
        : [restore, force];
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
        if (title !== undefined) this._confirmTitle = title;
        if (description !== undefined) this._confirmDescription = description;
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
