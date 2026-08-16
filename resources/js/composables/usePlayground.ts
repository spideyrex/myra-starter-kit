import { computed, ref, type Component, type ComputedRef, type Ref } from 'vue';

/**
 * CLOSED control union. A playground can only describe controls the renderer
 * already knows how to draw, so a spec cannot inject an arbitrary prop or an
 * arbitrary widget into the stage.
 */
export type PropControl =
    | { name: string; labelKey: string; kind: 'boolean'; default: boolean }
    | { name: string; labelKey: string; kind: 'number'; default: number; min: number; max: number; step?: number }
    | { name: string; labelKey: string; kind: 'text'; default: string; maxLength: number }
    | {
        name: string;
        labelKey: string;
        kind: 'select';
        default: string;
        options: ReadonlyArray<{ value: string; labelKey: string }>;
    };

export type SnippetLang = 'vue' | 'ts' | 'php';
export type PlaygroundViewport = 'sm' | 'md' | 'lg';
export type PlaygroundScheme = 'light' | 'dark' | 'split';
export type PlaygroundLocale = 'en' | 'ms' | 'zh';

export interface PlaygroundSpec<P extends Record<string, any> = any> {
    key: string;
    titleKey: string;
    component: Component | (() => Promise<any>);
    controls: PropControl[];
    /** slot name => control name whose text fills it. */
    slots?: Record<string, string>;
    /** PURE function → string. Rendered into <pre>, never v-html, never eval'd. */
    snippet: (values: P, lang: SnippetLang) => string;
}

export const VIEWPORT_WIDTHS: Record<PlaygroundViewport, number> = { sm: 375, md: 768, lg: 1280 };

export function definePlayground<P extends Record<string, any>>(s: PlaygroundSpec<P>): PlaygroundSpec<P> {
    return s;
}

function defaults<P extends Record<string, any>>(controls: PropControl[]): P {
    const out: Record<string, any> = {};
    for (const control of controls) out[control.name] = control.default;

    return out as P;
}

/** Coerce back into the control's own range — a stage never renders an out-of-spec prop. */
function coerce(control: PropControl, raw: unknown): any {
    switch (control.kind) {
        case 'boolean':
            return typeof raw === 'boolean' ? raw : control.default;
        case 'number': {
            const n = Number(raw);
            if (!Number.isFinite(n)) return control.default;
            return Math.min(control.max, Math.max(control.min, n));
        }
        case 'text': {
            const s = typeof raw === 'string' ? raw : String(raw ?? '');
            return s.slice(0, control.maxLength);
        }
        case 'select': {
            const s = String(raw ?? '');
            return control.options.some(o => o.value === s) ? s : control.default;
        }
    }
}

export function usePlayground<P extends Record<string, any>>(spec: PlaygroundSpec<P>): {
    values: Ref<P>;
    bound: ComputedRef<Record<string, any>>;
    snippet: ComputedRef<string>;
    lang: Ref<SnippetLang>;
    viewport: Ref<PlaygroundViewport>;
    scheme: Ref<PlaygroundScheme>;
    locale: Ref<PlaygroundLocale>;
    reset: () => void;
    copy: () => Promise<void>;
    copied: Ref<boolean>;
} {
    const values = ref(defaults<P>(spec.controls)) as Ref<P>;
    const lang = ref<SnippetLang>('vue');
    const viewport = ref<PlaygroundViewport>('lg');
    const scheme = ref<PlaygroundScheme>('light');
    const locale = ref<PlaygroundLocale>('en');
    const copied = ref(false);

    const bound = computed<Record<string, any>>(() => {
        const out: Record<string, any> = {};
        for (const control of spec.controls) {
            out[control.name] = coerce(control, (values.value as any)[control.name]);
        }

        return out;
    });

    const snippet = computed(() => {
        try {
            return spec.snippet(bound.value as P, lang.value);
        } catch {
            return '';
        }
    });

    function reset(): void {
        values.value = defaults<P>(spec.controls);
    }

    async function copy(): Promise<void> {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(snippet.value);
            } else {
                const ta = document.createElement('textarea');
                ta.value = snippet.value;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
        } catch {
            copied.value = false;
        }
    }

    return { values, bound, snippet, lang, viewport, scheme, locale, reset, copy, copied };
}
