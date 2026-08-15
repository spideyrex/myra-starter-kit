import type { Component } from 'vue';
import type { SelectOption } from '@/types/admin';
import { Check, X } from 'lucide-vue-next';

export type FieldType = 'text' | 'email' | 'password' | 'number' | 'textarea' | 'select' | 'switch' | 'checkbox' | 'tel' | 'url' | 'date' | 'datetime-local' | 'radio' | 'color' | 'hidden' | 'file' | 'richtext' | 'repeater' | 'builder' | 'slider' | 'number-field' | 'pin-input' | 'tags-input' | 'toggle-group' | 'calendar' | 'time' | 'checkbox-list' | 'key-value' | 'markdown' | 'code';

/** A block definition for the Builder field — a named, labelled group of fields. */
export interface BuilderBlock {
    name: string;
    label: string;
    icon?: string;
    schema: SchemaItem[];
}

export type VisibilityCondition = string | ((form: Record<string, any>) => boolean);

// --- Shared vocabulary (fields, columns and infolist entries all use these) ---

export type SemanticColor = 'muted' | 'primary' | 'info' | 'success' | 'warning' | 'danger';

/** Closed union. The `code` FIELD and the `code` ENTRY share it. */
export type CodeLanguage =
    | 'plaintext' | 'javascript' | 'typescript' | 'json' | 'html' | 'css'
    | 'php' | 'sql' | 'markdown' | 'yaml' | 'xml' | 'python' | 'bash' | 'vue';

export interface HintAction {
    label: string;
    icon?: Component;
    onClick: (form: Record<string, any>) => void;
}

export interface ToggleOption {
    value: string;
    label: string;
    icon?: Component;
    color?: SemanticColor;
    description?: string;
    tooltip?: string;
    disabled?: boolean;
    hidden?: boolean;
}

export const DEFAULT_MARKDOWN_TOOLBAR = [
    'bold', 'italic', 'strike', 'link',
    'heading', 'quote', 'code', 'codeBlock',
    'bulletList', 'orderedList', 'table', 'image',
    'undo', 'redo', 'mode',
] as const;

export type MarkdownButton = typeof DEFAULT_MARKDOWN_TOOLBAR[number] | 'hr' | 'fullscreen';

export type MarkdownMode = 'split' | 'edit' | 'preview';

export interface FieldSchema {
    name: string;
    label: string;
    type: FieldType;
    required: boolean;
    hint?: string;
    placeholder?: string;
    disabled: boolean;
    colSpan?: number;
    colStyle?: string;
    options?: SelectOption[];
    // Select: searchable combobox + async options endpoint
    searchable?: boolean;
    optionsUrl?: string;
    rows?: number;
    // Date fields
    minDate?: string;
    maxDate?: string;
    // Radio fields
    inline?: boolean;
    // File fields
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
    imageCrop?: boolean;
    imageAspectRatio?: number;
    imageOutputType?: string;
    imageOutputQuality?: number;
    // Rich text fields
    toolbar?: string[];
    editorPlaceholder?: string;
    // Repeater fields
    subSchema?: SchemaItem[];
    minItems?: number;
    maxItems?: number;
    addLabel?: string;
    reorderable?: boolean;
    repeaterCollapsible?: boolean;
    // Builder field (multi-type repeatable blocks)
    blocks?: BuilderBlock[];
    // Slider / NumberField
    min?: number;
    max?: number;
    step?: number;
    showValue?: boolean;
    formatOptions?: Intl.NumberFormatOptions;
    // PinInput
    pinLength?: number;
    pinMask?: boolean;
    // TagsInput
    maxTags?: number;
    tagPlaceholder?: string;
    // ToggleGroup
    toggleMultiple?: boolean;
    toggleVariant?: 'default' | 'outline';
    toggleOptions?: ToggleOption[];
    toggleColumns?: number;
    toggleSize?: 'sm' | 'default' | 'lg';
    toggleInline?: boolean;
    toggleHideLabels?: boolean;
    toggleMin?: number;
    toggleMax?: number;
    // Calendar date picker
    useCalendar?: boolean;
    dateFormat?: string;
    // TimePicker
    minTime?: string;
    maxTime?: string;
    // CheckboxList
    checkboxSearchable?: boolean;
    checkboxColumns?: number;
    // KeyValue
    keyLabel?: string;
    valueLabel?: string;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
    // Hint
    hintIcon?: Component;
    hintIconTooltip?: string;
    hintColor?: SemanticColor;
    hintAction?: HintAction;
    // Code
    codeLanguage?: CodeLanguage;
    codeLineNumbers?: boolean;
    codeWrap?: boolean;
    codeReadOnly?: boolean;
    codeIndentWithTab?: boolean;
    codeAutocomplete?: boolean;
    codeCopyable?: boolean;
    codeTabSize?: number;
    codeMinHeight?: string;
    codeMaxHeight?: string;
    codeFilename?: string;
    // Markdown
    mdToolbar?: MarkdownButton[];
    mdMode?: MarkdownMode;
    mdModeSwitcher?: boolean;
    mdFullscreen?: boolean;
    mdCounter?: boolean;
    mdMaxLength?: number;
    mdMinHeight?: string;
    mdMaxHeight?: string;
    mdUploadRoute?: string;
    mdMaxUploadKb?: number;
    mdAcceptedTypes?: string[];
    /** Laravel-style rule strings. Advisory on the client; the server rule is what counts. */
    rules?: string[];
    // Conditional visibility
    visibleWhen?: VisibilityCondition;
    hiddenWhen?: VisibilityCondition;
}

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

// --- Layout Types ---

export type LayoutType = 'section' | 'grid' | 'tabs' | 'tab' | 'fieldset' | 'flex' | 'wizard' | 'wizard-step' | 'callout';

export type ValidationRule = (value: any, form: Record<string, any>) => string | true;

export interface LayoutSchema {
    layoutType: LayoutType;
    label?: string;
    description?: string;
    columns?: number;
    collapsible?: boolean;
    collapsed?: boolean;
    icon?: Component;
    badge?: string;
    schema: SchemaItem[];
    variant?: 'info' | 'warning' | 'success' | 'danger';
    direction?: 'row' | 'col';
    gap?: number;
    justify?: string;
    align?: string;
    stepDescription?: string;
    validationRules?: Record<string, ValidationRule[]>;
}

export type SchemaItem = FieldSchema | BaseField | LayoutSchema | Section | Grid | Tabs | Fieldset | Flex | Wizard | Callout;
export type SchemaField = FieldSchema | BaseField;

// --- Layout Classes ---

export class Section {
    private _label: string;
    private _description?: string;
    private _columns = 2;
    private _collapsible = false;
    private _collapsed = false;
    private _icon?: Component;
    private _schema: SchemaItem[] = [];

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): Section {
        return new Section(label);
    }

    description(text: string): this {
        this._description = text;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    columns(n: number): this {
        this._columns = n;
        return this;
    }

    collapsible(value = true): this {
        this._collapsible = value;
        return this;
    }

    collapsed(value = true): this {
        this._collapsible = true;
        this._collapsed = value;
        return this;
    }

    icon(component: Component): this {
        this._icon = component;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'section',
            label: this._label,
            description: this._description,
            columns: this._columns,
            collapsible: this._collapsible,
            collapsed: this._collapsed,
            icon: this._icon,
            schema: this._schema,
        };
    }

    // Allow duck-typing as LayoutSchema
    get layoutType(): LayoutType { return 'section'; }
}

export class Grid {
    private _columns: number;
    private _schema: SchemaItem[] = [];

    constructor(columns = 2) {
        this._columns = columns;
    }

    static make(columns = 2): Grid {
        return new Grid(columns);
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'grid',
            columns: this._columns,
            schema: this._schema,
        };
    }

    get layoutType(): LayoutType { return 'grid'; }
}

export class Tab {
    private _label: string;
    private _icon?: Component;
    private _badge?: string;
    private _schema: SchemaItem[] = [];

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): Tab {
        return new Tab(label);
    }

    icon(component: Component): this {
        this._icon = component;
        return this;
    }

    badge(text: string): this {
        this._badge = text;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'tab',
            label: this._label,
            icon: this._icon,
            badge: this._badge,
            schema: this._schema,
        };
    }

    get layoutType(): LayoutType { return 'tab'; }
}

export class Tabs {
    private _tabs: Tab[] = [];

    constructor(tabs: Tab[]) {
        this._tabs = tabs;
    }

    static make(tabs: Tab[]): Tabs {
        return new Tabs(tabs);
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'tabs',
            schema: this._tabs.map(t => t.toLayout()) as unknown as SchemaItem[],
        };
    }

    get layoutType(): LayoutType { return 'tabs'; }
}

export class Fieldset {
    private _label: string;
    private _columns = 2;
    private _schema: SchemaItem[] = [];

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): Fieldset {
        return new Fieldset(label);
    }

    columns(n: number): this {
        this._columns = n;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'fieldset',
            label: this._label,
            columns: this._columns,
            schema: this._schema,
        };
    }

    get layoutType(): LayoutType { return 'fieldset'; }
}

export class Flex {
    private _direction: 'row' | 'col' = 'row';
    private _gap = 4;
    private _justify?: string;
    private _align?: string;
    private _schema: SchemaItem[] = [];

    static make(): Flex {
        return new Flex();
    }

    direction(d: 'row' | 'col'): this {
        this._direction = d;
        return this;
    }

    gap(n: number): this {
        this._gap = n;
        return this;
    }

    justify(j: string): this {
        this._justify = j;
        return this;
    }

    align(a: string): this {
        this._align = a;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'flex',
            direction: this._direction,
            gap: this._gap,
            justify: this._justify,
            align: this._align,
            schema: this._schema,
        };
    }

    get layoutType(): LayoutType { return 'flex'; }
}

export class WizardStep {
    private _label: string;
    private _description?: string;
    private _icon?: Component;
    private _columns?: number;
    private _schema: SchemaItem[] = [];
    private _validationRules: Record<string, ValidationRule[]> = {};

    constructor(label: string) {
        this._label = label;
    }

    static make(label: string): WizardStep {
        return new WizardStep(label);
    }

    description(text: string): this {
        this._description = text;
        return this;
    }

    icon(component: Component): this {
        this._icon = component;
        return this;
    }

    columns(n: number): this {
        this._columns = n;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    validate(rules: Record<string, ValidationRule[]>): this {
        this._validationRules = rules;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'wizard-step',
            label: this._label,
            stepDescription: this._description,
            icon: this._icon,
            columns: this._columns,
            schema: this._schema,
            validationRules: Object.keys(this._validationRules).length > 0 ? this._validationRules : undefined,
        };
    }

    get layoutType(): LayoutType { return 'wizard-step'; }
}

export class Wizard {
    private _steps: WizardStep[] = [];

    constructor(steps: WizardStep[]) {
        this._steps = steps;
    }

    static make(steps: WizardStep[]): Wizard {
        return new Wizard(steps);
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'wizard',
            schema: this._steps.map(s => s.toLayout()) as unknown as SchemaItem[],
        };
    }

    get layoutType(): LayoutType { return 'wizard'; }
}

export class Callout {
    private _label?: string;
    private _description?: string;
    private _variant: 'info' | 'warning' | 'success' | 'danger' = 'info';
    private _icon?: Component;
    private _schema: SchemaItem[] = [];

    constructor(label?: string) {
        this._label = label;
    }

    static make(label?: string): Callout {
        return new Callout(label);
    }

    description(text: string): this {
        this._description = text;
        return this;
    }

    variant(v: 'info' | 'warning' | 'success' | 'danger'): this {
        this._variant = v;
        return this;
    }

    icon(component: Component): this {
        this._icon = component;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    info(): this {
        this._variant = 'info';
        return this;
    }

    warning(): this {
        this._variant = 'warning';
        return this;
    }

    success(): this {
        this._variant = 'success';
        return this;
    }

    danger(): this {
        this._variant = 'danger';
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'callout',
            label: this._label,
            description: this._description,
            variant: this._variant,
            icon: this._icon,
            schema: this._schema,
        };
    }

    get layoutType(): LayoutType { return 'callout'; }
}

// --- Field Classes ---

export class BaseField {
    protected _name: string;
    protected _label?: string;
    protected _type: FieldType = 'text';
    protected _required = false;
    protected _hint?: string;
    protected _hintIcon?: Component;
    protected _hintIconTooltip?: string;
    protected _hintColor: SemanticColor = 'muted';
    protected _hintAction?: HintAction;
    protected _placeholder?: string;
    protected _disabled = false;
    protected _colSpan?: number;
    protected _visibleWhen?: VisibilityCondition;
    protected _hiddenWhen?: VisibilityCondition;
    protected _rules: string[] = [];

    constructor(name: string) {
        this._name = name;
    }

    get name(): string {
        return this._name;
    }

    get colStyle(): string | undefined {
        return this._colSpan ? `grid-column: span ${this._colSpan}` : undefined;
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    required(): this {
        this._required = true;
        return this;
    }

    /** Helper text below the control. THE helper-text concept — there is no `helperText()` alias. */
    hint(text: string): this {
        this._hint = text;
        return this;
    }

    hintIcon(icon: Component, tooltip?: string): this {
        this._hintIcon = icon;
        if (tooltip) this._hintIconTooltip = tooltip;
        return this;
    }

    hintColor(color: SemanticColor): this {
        this._hintColor = color;
        return this;
    }

    hintAction(action: HintAction): this {
        this._hintAction = action;
        return this;
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    disabled(value = true): this {
        this._disabled = value;
        return this;
    }

    colSpan(n: number): this {
        this._colSpan = n;
        return this;
    }

    visibleWhen(condition: VisibilityCondition): this {
        this._visibleWhen = condition;
        return this;
    }

    hiddenWhen(condition: VisibilityCondition): this {
        this._hiddenWhen = condition;
        return this;
    }

    /** Laravel-style rule strings, mirrored into the FormRequest by the generators. */
    rules(rules: string[]): this {
        this._rules = [...this._rules, ...rules];
        return this;
    }

    toProps(): FieldSchema {
        return {
            name: this._name,
            label: this._label ?? humanize(this._name),
            type: this._type,
            required: this._required,
            hint: this._hint,
            hintIcon: this._hintIcon,
            hintIconTooltip: this._hintIconTooltip,
            hintColor: this._hintColor,
            hintAction: this._hintAction,
            placeholder: this._placeholder,
            disabled: this._disabled,
            colSpan: this._colSpan,
            visibleWhen: this._visibleWhen,
            hiddenWhen: this._hiddenWhen,
            rules: this._rules.length ? [...this._rules] : undefined,
        };
    }
}

export class TextInput extends BaseField {
    static make(name: string): TextInput {
        return new TextInput(name);
    }

    email(): this {
        this._type = 'email';
        return this;
    }

    password(): this {
        this._type = 'password';
        return this;
    }

    numeric(): this {
        this._type = 'number';
        return this;
    }

    integer(): this {
        this._type = 'number';
        return this;
    }

    tel(): this {
        this._type = 'tel';
        return this;
    }

    url(): this {
        this._type = 'url';
        return this;
    }
}

export class Select extends BaseField {
    private _options: SelectOption[] = [];
    private _searchable = false;
    private _optionsUrl?: string;

    constructor(name: string) {
        super(name);
        this._type = 'select';
    }

    static make(name: string): Select {
        return new Select(name);
    }

    options(opts: SelectOption[] | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    /** Render a searchable combobox instead of a plain select. */
    searchable(value = true): this {
        this._searchable = value;
        return this;
    }

    /**
     * Load options from an endpoint as the user types (implies searchable).
     * The endpoint receives `?search=` and must return `[{ value, label }]`
     * (or `{ data: [...] }`). Ideal for relationship pickers.
     */
    optionsUrl(url: string): this {
        this._optionsUrl = url;
        this._searchable = true;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            options: this._options,
            searchable: this._searchable,
            optionsUrl: this._optionsUrl,
        };
    }
}

export class Textarea extends BaseField {
    private _rows?: number;

    constructor(name: string) {
        super(name);
        this._type = 'textarea';
    }

    static make(name: string): Textarea {
        return new Textarea(name);
    }

    rows(n: number): this {
        this._rows = n;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), rows: this._rows };
    }
}

export class Toggle extends BaseField {
    constructor(name: string) {
        super(name);
        this._type = 'switch';
    }

    static make(name: string): Toggle {
        return new Toggle(name);
    }
}

export class Checkbox extends BaseField {
    constructor(name: string) {
        super(name);
        this._type = 'checkbox';
    }

    static make(name: string): Checkbox {
        return new Checkbox(name);
    }
}

// --- New Field Types ---

export class DatePicker extends BaseField {
    private _minDate?: string;
    private _maxDate?: string;
    private _useCalendar = false;

    constructor(name: string) {
        super(name);
        this._type = 'date';
    }

    static make(name: string): DatePicker {
        return new DatePicker(name);
    }

    minDate(d: string): this {
        this._minDate = d;
        return this;
    }

    maxDate(d: string): this {
        this._maxDate = d;
        return this;
    }

    useCalendar(): this {
        this._useCalendar = true;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), minDate: this._minDate, maxDate: this._maxDate, useCalendar: this._useCalendar };
    }
}

export class DateTimePicker extends BaseField {
    private _minDate?: string;
    private _maxDate?: string;
    private _useCalendar = false;

    constructor(name: string) {
        super(name);
        this._type = 'datetime-local';
    }

    static make(name: string): DateTimePicker {
        return new DateTimePicker(name);
    }

    minDate(d: string): this {
        this._minDate = d;
        return this;
    }

    maxDate(d: string): this {
        this._maxDate = d;
        return this;
    }

    useCalendar(): this {
        this._useCalendar = true;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), minDate: this._minDate, maxDate: this._maxDate, useCalendar: this._useCalendar };
    }
}

export class Radio extends BaseField {
    private _options: SelectOption[] = [];
    private _inline = false;

    constructor(name: string) {
        super(name);
        this._type = 'radio';
    }

    static make(name: string): Radio {
        return new Radio(name);
    }

    options(opts: SelectOption[] | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    inline(value = true): this {
        this._inline = value;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), options: this._options, inline: this._inline };
    }
}

export class ColorPicker extends BaseField {
    constructor(name: string) {
        super(name);
        this._type = 'color';
    }

    static make(name: string): ColorPicker {
        return new ColorPicker(name);
    }
}

export class Hidden extends BaseField {
    constructor(name: string) {
        super(name);
        this._type = 'hidden';
    }

    static make(name: string): Hidden {
        return new Hidden(name);
    }
}

export class FileUpload extends BaseField {
    private _accept?: string;
    private _multiple = false;
    private _maxSize?: number;
    private _imageCrop = false;
    private _imageAspectRatio?: number;
    private _imageOutputType?: string;
    private _imageOutputQuality?: number;

    constructor(name: string) {
        super(name);
        this._type = 'file';
    }

    static make(name: string): FileUpload {
        return new FileUpload(name);
    }

    accept(types: string): this {
        this._accept = types;
        return this;
    }

    multiple(value = true): this {
        this._multiple = value;
        return this;
    }

    maxSize(mb: number): this {
        this._maxSize = mb;
        return this;
    }

    image(): this {
        this._accept = 'image/*';
        return this;
    }

    imageCrop(value = true): this {
        this._imageCrop = value;
        if (value && !this._accept) this._accept = 'image/*';
        return this;
    }

    imageAspectRatio(ratio: number): this {
        this._imageAspectRatio = ratio;
        return this;
    }

    imageOutputType(type: string): this {
        this._imageOutputType = type;
        return this;
    }

    imageOutputQuality(quality: number): this {
        this._imageOutputQuality = quality;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            accept: this._accept,
            multiple: this._multiple,
            maxSize: this._maxSize,
            imageCrop: this._imageCrop,
            imageAspectRatio: this._imageAspectRatio,
            imageOutputType: this._imageOutputType,
            imageOutputQuality: this._imageOutputQuality,
        };
    }
}

export class RichEditor extends BaseField {
    private _rows = 6;
    private _toolbar?: string[];
    private _editorPlaceholder?: string;

    constructor(name: string) {
        super(name);
        this._type = 'richtext';
    }

    static make(name: string): RichEditor {
        return new RichEditor(name);
    }

    rows(n: number): this {
        this._rows = n;
        return this;
    }

    toolbar(items: string[]): this {
        this._toolbar = items;
        return this;
    }

    editorPlaceholder(text: string): this {
        this._editorPlaceholder = text;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), rows: this._rows, toolbar: this._toolbar, editorPlaceholder: this._editorPlaceholder };
    }
}

export class Repeater extends BaseField {
    private _subSchema: SchemaItem[] = [];
    private _minItems?: number;
    private _maxItems?: number;
    private _addLabel = 'Add Item';
    private _reorderable = true;
    private _collapsibleItems = false;

    constructor(name: string) {
        super(name);
        this._type = 'repeater';
    }

    static make(name: string): Repeater {
        return new Repeater(name);
    }

    schema(fields: SchemaItem[]): this {
        this._subSchema = fields;
        return this;
    }

    minItems(n: number): this {
        this._minItems = n;
        return this;
    }

    maxItems(n: number): this {
        this._maxItems = n;
        return this;
    }

    addLabel(text: string): this {
        this._addLabel = text;
        return this;
    }

    reorderable(value = true): this {
        this._reorderable = value;
        return this;
    }

    collapsible(value = true): this {
        this._collapsibleItems = value;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            subSchema: this._subSchema,
            minItems: this._minItems,
            maxItems: this._maxItems,
            addLabel: this._addLabel,
            reorderable: this._reorderable,
            repeaterCollapsible: this._collapsibleItems,
        };
    }
}

// --- Block Builder (multi-type repeatable blocks, Filament-style) ---

export class Block {
    private _name: string;
    private _label?: string;
    private _icon?: string;
    private _schema: SchemaItem[] = [];

    constructor(name: string) {
        this._name = name;
    }

    static make(name: string): Block {
        return new Block(name);
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    /** lucide-vue-next icon name shown in the add-block menu. */
    icon(name: string): this {
        this._icon = name;
        return this;
    }

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toBlock(): BuilderBlock {
        return {
            name: this._name,
            label: this._label ?? humanize(this._name),
            icon: this._icon,
            schema: this._schema,
        };
    }
}

/**
 * Builder — a repeatable list where each item can be a different "block" type.
 * Stored value shape: `[{ type: blockName, data: { ...fields } }, ...]`.
 */
export class Builder extends BaseField {
    private _blocks: Block[] = [];
    private _minItems?: number;
    private _maxItems?: number;
    private _addLabel = 'Add Block';
    private _reorderable = true;
    private _collapsibleItems = false;

    constructor(name: string) {
        super(name);
        this._type = 'builder';
    }

    static make(name: string): Builder {
        return new Builder(name);
    }

    blocks(blocks: Block[]): this {
        this._blocks = blocks;
        return this;
    }

    minItems(n: number): this {
        this._minItems = n;
        return this;
    }

    maxItems(n: number): this {
        this._maxItems = n;
        return this;
    }

    addLabel(text: string): this {
        this._addLabel = text;
        return this;
    }

    reorderable(value = true): this {
        this._reorderable = value;
        return this;
    }

    collapsible(value = true): this {
        this._collapsibleItems = value;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            blocks: this._blocks.map(b => b.toBlock()),
            minItems: this._minItems,
            maxItems: this._maxItems,
            addLabel: this._addLabel,
            reorderable: this._reorderable,
            repeaterCollapsible: this._collapsibleItems,
        };
    }
}

// --- New shadcn-vue Field Types ---

export class Slider extends BaseField {
    private _min = 0;
    private _max = 100;
    private _step = 1;
    private _showValue = false;

    constructor(name: string) {
        super(name);
        this._type = 'slider';
    }

    static make(name: string): Slider {
        return new Slider(name);
    }

    min(n: number): this {
        this._min = n;
        return this;
    }

    max(n: number): this {
        this._max = n;
        return this;
    }

    step(n: number): this {
        this._step = n;
        return this;
    }

    showValue(): this {
        this._showValue = true;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), min: this._min, max: this._max, step: this._step, showValue: this._showValue };
    }
}

export class NumberField extends BaseField {
    private _min?: number;
    private _max?: number;
    private _step = 1;
    private _formatOptions?: Intl.NumberFormatOptions;

    constructor(name: string) {
        super(name);
        this._type = 'number-field';
    }

    static make(name: string): NumberField {
        return new NumberField(name);
    }

    min(n: number): this {
        this._min = n;
        return this;
    }

    max(n: number): this {
        this._max = n;
        return this;
    }

    step(n: number): this {
        this._step = n;
        return this;
    }

    formatOptions(opts: Intl.NumberFormatOptions): this {
        this._formatOptions = opts;
        return this;
    }

    currency(code: string): this {
        this._formatOptions = { style: 'currency', currency: code, currencyDisplay: 'symbol' };
        return this;
    }

    percent(): this {
        this._formatOptions = { style: 'percent' };
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), min: this._min, max: this._max, step: this._step, formatOptions: this._formatOptions };
    }
}

export class PinInput extends BaseField {
    private _pinLength = 6;
    private _pinMask = false;

    constructor(name: string) {
        super(name);
        this._type = 'pin-input';
    }

    static make(name: string): PinInput {
        return new PinInput(name);
    }

    length(n: number): this {
        this._pinLength = n;
        return this;
    }

    mask(): this {
        this._pinMask = true;
        return this;
    }

    otp(): this {
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), pinLength: this._pinLength, pinMask: this._pinMask };
    }
}

export class TagsInput extends BaseField {
    private _maxTags?: number;
    private _tagPlaceholder?: string;

    constructor(name: string) {
        super(name);
        this._type = 'tags-input';
    }

    static make(name: string): TagsInput {
        return new TagsInput(name);
    }

    maxTags(n: number): this {
        this._maxTags = n;
        return this;
    }

    tagPlaceholder(text: string): this {
        this._tagPlaceholder = text;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), maxTags: this._maxTags, tagPlaceholder: this._tagPlaceholder };
    }
}

/** Multi-select min()/max() only bind if they reach the server as rules. Explicit rules win. */
function selectionRules(multiple: boolean, min: number | undefined, max: number | undefined, explicit: string[]): string[] | undefined {
    const auto: string[] = [];
    if (multiple) auto.push('array');
    if (min !== undefined) auto.push(`min:${min}`);
    if (max !== undefined) auto.push(`max:${max}`);
    const all = [...auto, ...explicit];
    return all.length ? all : undefined;
}

export class ToggleGroupField extends BaseField {
    protected _options: SelectOption[] = [];
    protected _toggleMultiple = false;
    protected _toggleVariant: 'default' | 'outline' = 'outline';
    protected _toggleOptions: ToggleOption[] = [];
    protected _toggleColumns?: number;
    protected _toggleSize: 'sm' | 'default' | 'lg' = 'default';
    protected _toggleInline = false;
    protected _toggleHideLabels = false;
    protected _toggleMin?: number;
    protected _toggleMax?: number;

    constructor(name: string) {
        super(name);
        this._type = 'toggle-group';
    }

    static make(name: string): ToggleGroupField {
        return new ToggleGroupField(name);
    }

    /** Accepts the rich descriptor array, SelectOption[], or a record. */
    options(opts: ToggleOption[] | SelectOption[] | Record<string, string>): this {
        const normalised: ToggleOption[] = Array.isArray(opts)
            ? (opts as any[]).map(o => ({ ...o, value: String(o.value), label: o.label ?? String(o.value) }))
            : Object.entries(opts).map(([value, label]) => ({ value, label }));
        this._toggleOptions = normalised;
        this._options = normalised.map(o => ({ label: o.label, value: o.value }));
        return this;
    }

    multiple(value = true): this {
        this._toggleMultiple = value;
        return this;
    }

    variant(v: 'default' | 'outline'): this {
        this._toggleVariant = v;
        return this;
    }

    size(s: 'sm' | 'default' | 'lg'): this {
        this._toggleSize = s;
        return this;
    }

    inline(value = true): this {
        this._toggleInline = value;
        return this;
    }

    columns(n: number): this {
        this._toggleColumns = n;
        return this;
    }

    /** Icon-only buttons. The option label becomes the accessible name. */
    hideLabels(value = true): this {
        this._toggleHideLabels = value;
        return this;
    }

    min(n: number): this {
        this._toggleMin = n;
        return this;
    }

    max(n: number): this {
        this._toggleMax = n;
        return this;
    }

    /** Preset that WRITES options, so it composes instead of being a mode. */
    boolean(trueLabel = 'Yes', falseLabel = 'No'): this {
        return this.options([
            { value: '1', label: trueLabel, color: 'success', icon: Check },
            { value: '0', label: falseLabel, color: 'danger', icon: X },
        ]).variant('outline');
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            options: this._options,
            toggleOptions: this._toggleOptions.filter(o => !o.hidden),
            toggleMultiple: this._toggleMultiple,
            toggleVariant: this._toggleVariant,
            toggleColumns: this._toggleColumns,
            toggleSize: this._toggleSize,
            toggleInline: this._toggleInline,
            toggleHideLabels: this._toggleHideLabels,
            toggleMin: this._toggleMin,
            toggleMax: this._toggleMax,
            rules: selectionRules(this._toggleMultiple, this._toggleMin, this._toggleMax, this._rules),
        };
    }
}

/** Filament-familiar name. A real subclass, so class.name matches in stack traces. */
export class ToggleButtons extends ToggleGroupField {
    static make(name: string): ToggleButtons {
        return new ToggleButtons(name);
    }
}

export class CalendarPicker extends BaseField {
    private _minDate?: string;
    private _maxDate?: string;
    private _dateFormat?: string;

    constructor(name: string) {
        super(name);
        this._type = 'calendar';
    }

    static make(name: string): CalendarPicker {
        return new CalendarPicker(name);
    }

    minDate(d: string): this {
        this._minDate = d;
        return this;
    }

    maxDate(d: string): this {
        this._maxDate = d;
        return this;
    }

    dateFormat(fmt: string): this {
        this._dateFormat = fmt;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), minDate: this._minDate, maxDate: this._maxDate, dateFormat: this._dateFormat, useCalendar: true };
    }
}

// --- Additional Field Types ---

export class TimePicker extends BaseField {
    private _minTime?: string;
    private _maxTime?: string;

    constructor(name: string) {
        super(name);
        this._type = 'time';
    }

    static make(name: string): TimePicker {
        return new TimePicker(name);
    }

    minTime(t: string): this {
        this._minTime = t;
        return this;
    }

    maxTime(t: string): this {
        this._maxTime = t;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), minTime: this._minTime, maxTime: this._maxTime };
    }
}

export class CheckboxList extends BaseField {
    private _options: SelectOption[] = [];
    private _searchable = false;
    private _columns = 1;
    private _min?: number;
    private _max?: number;

    constructor(name: string) {
        super(name);
        this._type = 'checkbox-list';
    }

    static make(name: string): CheckboxList {
        return new CheckboxList(name);
    }

    options(opts: SelectOption[] | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    searchable(value = true): this {
        this._searchable = value;
        return this;
    }

    columns(n: number): this {
        this._columns = n;
        return this;
    }

    min(n: number): this {
        this._min = n;
        return this;
    }

    max(n: number): this {
        this._max = n;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            options: this._options,
            checkboxSearchable: this._searchable,
            checkboxColumns: this._columns,
            toggleMin: this._min,
            toggleMax: this._max,
            rules: selectionRules(true, this._min, this._max, this._rules),
        };
    }
}

export class KeyValue extends BaseField {
    private _keyLabel = 'Key';
    private _valueLabel = 'Value';
    private _keyPlaceholder = 'Enter key...';
    private _valuePlaceholder = 'Enter value...';
    private _maxItems?: number;

    constructor(name: string) {
        super(name);
        this._type = 'key-value';
    }

    static make(name: string): KeyValue {
        return new KeyValue(name);
    }

    keyLabel(text: string): this {
        this._keyLabel = text;
        return this;
    }

    valueLabel(text: string): this {
        this._valueLabel = text;
        return this;
    }

    keyPlaceholder(text: string): this {
        this._keyPlaceholder = text;
        return this;
    }

    valuePlaceholder(text: string): this {
        this._valuePlaceholder = text;
        return this;
    }

    maxItems(n: number): this {
        this._maxItems = n;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            keyLabel: this._keyLabel,
            valueLabel: this._valueLabel,
            keyPlaceholder: this._keyPlaceholder,
            valuePlaceholder: this._valuePlaceholder,
            maxItems: this._maxItems,
        };
    }
}

export class MarkdownEditor extends BaseField {
    private _rows = 10;
    private _mdToolbar?: MarkdownButton[];
    private _mdWithout: MarkdownButton[] = [];
    private _mdMode: MarkdownMode = 'split';
    private _mdModeSwitcher = true;
    private _mdFullscreen = false;
    private _mdCounter = false;
    private _mdMaxLength?: number;
    private _mdMinHeight?: string;
    private _mdMaxHeight?: string;
    private _mdUploadRoute?: string;
    private _mdMaxUploadKb = 5120;
    private _mdAcceptedTypes: string[] = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    constructor(name: string) {
        super(name);
        this._type = 'markdown';
    }

    static make(name: string): MarkdownEditor {
        return new MarkdownEditor(name);
    }

    rows(n: number): this {
        this._rows = n;
        return this;
    }

    /** REPLACES the default set. */
    toolbar(buttons: MarkdownButton[]): this {
        this._mdToolbar = buttons;
        return this;
    }

    /** SUBTRACTS from whatever set is active. Resolved in toProps(), so order does not matter. */
    withoutToolbar(buttons: MarkdownButton[]): this {
        this._mdWithout = [...this._mdWithout, ...buttons];
        return this;
    }

    mode(m: MarkdownMode): this {
        this._mdMode = m;
        return this;
    }

    modeSwitcher(value = true): this {
        this._mdModeSwitcher = value;
        return this;
    }

    fullscreen(value = true): this {
        this._mdFullscreen = value;
        return this;
    }

    counter(value = true): this {
        this._mdCounter = value;
        return this;
    }

    maxLength(n: number): this {
        this._mdMaxLength = n;
        this._mdCounter = true;
        return this;
    }

    minHeight(css: string): this {
        this._mdMinHeight = css;
        return this;
    }

    maxHeight(css: string): this {
        this._mdMaxHeight = css;
        return this;
    }

    /** Upload endpoint for pasted/dropped images. Defaults to the shipped route. */
    uploadRoute(routeName = 'admin.uploads.inline'): this {
        this._mdUploadRoute = routeName;
        return this;
    }

    maxUploadKb(kb: number): this {
        this._mdMaxUploadKb = kb;
        return this;
    }

    acceptedUploadTypes(mimes: string[]): this {
        this._mdAcceptedTypes = mimes;
        return this;
    }

    toProps(): FieldSchema {
        const base = this._mdToolbar ?? [...DEFAULT_MARKDOWN_TOOLBAR];
        return {
            ...super.toProps(),
            rows: this._rows,
            mdToolbar: base.filter(b => !this._mdWithout.includes(b)),
            mdMode: this._mdMode,
            mdModeSwitcher: this._mdModeSwitcher,
            mdFullscreen: this._mdFullscreen,
            mdCounter: this._mdCounter,
            mdMaxLength: this._mdMaxLength,
            mdMinHeight: this._mdMinHeight,
            mdMaxHeight: this._mdMaxHeight,
            mdUploadRoute: this._mdUploadRoute,
            mdMaxUploadKb: this._mdMaxUploadKb,
            mdAcceptedTypes: [...this._mdAcceptedTypes],
        };
    }
}

export class CodeEditor extends BaseField {
    private _codeLanguage: CodeLanguage = 'plaintext';
    private _codeLineNumbers = true;
    private _codeWrap = true;
    private _codeReadOnly = false;
    private _codeIndentWithTab = false;
    private _codeAutocomplete = false;
    private _codeCopyable = false;
    private _codeTabSize = 2;
    private _codeMinHeight = '12rem';
    private _codeMaxHeight?: string;
    private _codeFilename?: string;

    constructor(name: string) {
        super(name);
        this._type = 'code';
    }

    static make(name: string): CodeEditor {
        return new CodeEditor(name);
    }

    language(lang: CodeLanguage): this {
        this._codeLanguage = lang;
        return this;
    }

    lineNumbers(value = true): this {
        this._codeLineNumbers = value;
        return this;
    }

    wrap(value = true): this {
        this._codeWrap = value;
        return this;
    }

    readOnly(value = true): this {
        this._codeReadOnly = value;
        return this;
    }

    /** OFF by default: Tab must keep traversing the form. */
    indentWithTab(value = true): this {
        this._codeIndentWithTab = value;
        return this;
    }

    /** Opt-in; lazily pulls @codemirror/autocomplete. */
    autocomplete(value = true): this {
        this._codeAutocomplete = value;
        return this;
    }

    copyable(value = true): this {
        this._codeCopyable = value;
        return this;
    }

    tabSize(n: number): this {
        this._codeTabSize = n;
        return this;
    }

    minHeight(css: string): this {
        this._codeMinHeight = css;
        return this;
    }

    maxHeight(css: string): this {
        this._codeMaxHeight = css;
        return this;
    }

    filename(name: string): this {
        this._codeFilename = name;
        return this;
    }

    toProps(): FieldSchema {
        return {
            ...super.toProps(),
            codeLanguage: this._codeLanguage,
            codeLineNumbers: this._codeLineNumbers,
            codeWrap: this._codeWrap,
            codeReadOnly: this._codeReadOnly,
            codeIndentWithTab: this._codeIndentWithTab,
            codeAutocomplete: this._codeAutocomplete,
            codeCopyable: this._codeCopyable,
            codeTabSize: this._codeTabSize,
            codeMinHeight: this._codeMinHeight,
            codeMaxHeight: this._codeMaxHeight,
            codeFilename: this._codeFilename,
        };
    }
}

// --- Helpers ---

export function isLayoutItem(item: SchemaItem): item is LayoutSchema {
    if (item && typeof item === 'object' && 'layoutType' in item) return true;
    if (item instanceof Section || item instanceof Grid || item instanceof Tabs || item instanceof Fieldset || item instanceof Flex || item instanceof Wizard || item instanceof Callout) return true;
    return false;
}

export function resolveLayout(item: Section | Grid | Tabs | Fieldset | Flex | Wizard | Callout | LayoutSchema): LayoutSchema {
    if ('toLayout' in item && typeof item.toLayout === 'function') {
        return item.toLayout();
    }
    return item as LayoutSchema;
}

/**
 * Recursively collect field names from a schema tree.
 */
export function collectFieldNames(items: SchemaItem[]): string[] {
    const names: string[] = [];
    for (const item of items) {
        if (item instanceof BaseField) {
            names.push(item.name);
        } else if (isLayoutItem(item)) {
            const layout = resolveLayout(item as any);
            names.push(...collectFieldNames(layout.schema));
        } else if ('name' in item && typeof (item as any).name === 'string') {
            names.push((item as any).name);
        }
    }
    return names;
}

/**
 * Evaluate a visibility condition against form data.
 * String conditions: 'type:business' (equals), '!type:personal' (not equals),
 * 'type:business,country:us' (AND), 'status:active|pending' (OR values)
 * Function conditions: (form) => form.amount > 1000
 */
export function evaluateVisibility(condition: VisibilityCondition, form: Record<string, any>): boolean {
    if (typeof condition === 'function') {
        return condition(form);
    }

    // String-based conditions: comma-separated AND pairs
    const pairs = condition.split(',').map(s => s.trim());
    return pairs.every(pair => {
        const negated = pair.startsWith('!');
        const clean = negated ? pair.slice(1) : pair;
        const [key, valuesStr] = clean.split(':');
        if (!key || valuesStr === undefined) return true;

        // Pipe-separated OR values: 'status:active|pending'
        const allowedValues = valuesStr.split('|');
        const formValue = String(form[key] ?? '');
        const matches = allowedValues.includes(formValue);
        return negated ? !matches : matches;
    });
}
