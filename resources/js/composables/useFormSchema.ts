import type { Component } from 'vue';
import type { SelectOption } from '@/types/admin';

export type FieldType = 'text' | 'email' | 'password' | 'number' | 'textarea' | 'select' | 'switch' | 'checkbox' | 'tel' | 'url' | 'date' | 'datetime-local' | 'radio' | 'color' | 'hidden' | 'file' | 'richtext' | 'repeater' | 'slider' | 'number-field' | 'pin-input' | 'tags-input' | 'toggle-group' | 'calendar' | 'time' | 'checkbox-list' | 'key-value' | 'markdown';

export type VisibilityCondition = string | ((form: Record<string, any>) => boolean);

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
    private _schema: SchemaItem[] = [];

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

    schema(fields: SchemaItem[]): this {
        this._schema = fields;
        return this;
    }

    toLayout(): LayoutSchema {
        return {
            layoutType: 'wizard-step',
            label: this._label,
            stepDescription: this._description,
            icon: this._icon,
            schema: this._schema,
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
    protected _placeholder?: string;
    protected _disabled = false;
    protected _colSpan?: number;
    protected _visibleWhen?: VisibilityCondition;
    protected _hiddenWhen?: VisibilityCondition;

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

    hint(text: string): this {
        this._hint = text;
        return this;
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    disabled(): this {
        this._disabled = true;
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

    toProps(): FieldSchema {
        return {
            name: this._name,
            label: this._label ?? humanize(this._name),
            type: this._type,
            required: this._required,
            hint: this._hint,
            placeholder: this._placeholder,
            disabled: this._disabled,
            colSpan: this._colSpan,
            visibleWhen: this._visibleWhen,
            hiddenWhen: this._hiddenWhen,
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

    toProps(): FieldSchema {
        return { ...super.toProps(), options: this._options };
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

    toProps(): FieldSchema {
        return { ...super.toProps(), accept: this._accept, multiple: this._multiple, maxSize: this._maxSize };
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

export class ToggleGroupField extends BaseField {
    private _options: SelectOption[] = [];
    private _toggleMultiple = false;
    private _toggleVariant: 'default' | 'outline' = 'outline';

    constructor(name: string) {
        super(name);
        this._type = 'toggle-group';
    }

    static make(name: string): ToggleGroupField {
        return new ToggleGroupField(name);
    }

    options(opts: SelectOption[] | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    multiple(): this {
        this._toggleMultiple = true;
        return this;
    }

    variant(v: 'default' | 'outline'): this {
        this._toggleVariant = v;
        return this;
    }

    toProps(): FieldSchema {
        return { ...super.toProps(), options: this._options, toggleMultiple: this._toggleMultiple, toggleVariant: this._toggleVariant };
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

    toProps(): FieldSchema {
        return { ...super.toProps(), options: this._options, checkboxSearchable: this._searchable, checkboxColumns: this._columns };
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

    toProps(): FieldSchema {
        return { ...super.toProps(), rows: this._rows };
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
