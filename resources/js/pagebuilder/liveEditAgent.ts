import {
    envelope,
    isInlineKind,
    unwrap,
    type FieldKind,
    type FrameMessage,
    type HostMessage,
} from './liveEditProtocol';

/**
 * Runs INSIDE the preview iframe, on the real public homepage.
 *
 * It never mounts unless the page is framed by the builder AND the builder
 * explicitly enables it, so an anonymous visitor — or an author opening the
 * preview in a new tab — gets an ordinary, inert page.
 *
 * Fields are found by the `data-myra-field` markers the section renderers emit;
 * the owning block is the nearest `[data-myra-block]` ancestor.
 */

const FIELD_SELECTOR = '[data-myra-block] [data-myra-field]';
const STYLE_ID = 'myra-live-edit-style';
const COMMIT_DEBOUNCE_MS = 250;

const CSS = `
[data-myra-live] [data-myra-block] [data-myra-field] {
    outline: 1px dashed color-mix(in oklch, currentColor 35%, transparent);
    outline-offset: 3px;
    border-radius: 2px;
    transition: outline-color 120ms ease, background-color 120ms ease;
}
[data-myra-live] [data-myra-block] [data-myra-field]:hover {
    outline: 2px solid var(--primary, #3b82f6);
    outline-offset: 3px;
    cursor: text;
}
[data-myra-live] [data-myra-block] [data-myra-field][data-myra-activatable]:hover { cursor: pointer; }
[data-myra-live] [data-myra-block] [data-myra-field]:focus {
    outline: 2px solid var(--primary, #3b82f6);
    outline-offset: 3px;
    background-color: color-mix(in oklch, var(--primary, #3b82f6) 8%, transparent);
}
[data-myra-live] [data-myra-block][data-myra-highlight] {
    outline: 2px solid var(--primary, #3b82f6);
    outline-offset: -2px;
}
[data-myra-live] [data-myra-block] [data-myra-field]:empty::after {
    content: attr(data-myra-placeholder);
    opacity: 0.45;
}
`;

let active = false;
let teardown: Array<() => void> = [];
let commitTimer: ReturnType<typeof setTimeout> | null = null;

function post(message: FrameMessage): void {
    try {
        window.parent.postMessage(envelope(message), window.location.origin);
    } catch {
        // A detached or cross-origin parent is not worth throwing over.
    }
}

function blockOf(el: Element): string {
    const owner = el.closest<HTMLElement>('[data-myra-block]');

    return owner?.dataset.myraBlock ?? '';
}

function kindOf(el: HTMLElement): FieldKind {
    const kind = el.dataset.myraKind ?? 'text';

    return (['text', 'multiline', 'html', 'image', 'link'] as const).includes(kind as FieldKind)
        ? (kind as FieldKind)
        : 'text';
}

/**
 * innerText reflects what the reader actually sees, but it is not universal
 * (jsdom implements none), so textContent carries the fallback.
 */
function readText(el: HTMLElement): string {
    return typeof el.innerText === 'string' ? el.innerText : (el.textContent ?? '');
}

function writeText(el: HTMLElement, value: string): void {
    if (typeof el.innerText === 'string') {
        el.innerText = value;

        return;
    }

    el.textContent = value;
}

/** Rendered text, normalised. Single-line kinds never keep a newline. */
function readValue(el: HTMLElement, kind: FieldKind): string {
    const text = readText(el).replace(/ /g, ' ');

    return kind === 'multiline' ? text.replace(/\s+$/, '') : text.replace(/\s*\n+\s*/g, ' ').trim();
}

function injectStyle(): void {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = CSS;
    document.head.appendChild(style);
}

function commit(el: HTMLElement, immediate = false): void {
    const block = blockOf(el);
    const field = el.dataset.myraField ?? '';

    if (block === '' || field === '') return;

    const send = () => post({ type: 'change', block, field, value: readValue(el, kindOf(el)) });

    if (commitTimer !== null) {
        clearTimeout(commitTimer);
        commitTimer = null;
    }

    if (immediate) {
        send();

        return;
    }

    commitTimer = setTimeout(() => {
        commitTimer = null;
        send();
    }, COMMIT_DEBOUNCE_MS);
}

function on<K extends keyof HTMLElementEventMap>(
    target: HTMLElement | Document | Window,
    event: K,
    handler: (e: HTMLElementEventMap[K]) => void,
    options?: AddEventListenerOptions,
): void {
    target.addEventListener(event, handler as EventListener, options);
    teardown.push(() => target.removeEventListener(event, handler as EventListener, options));
}

function bindInline(el: HTMLElement, kind: 'text' | 'multiline'): void {
    // plaintext-only keeps pasted markup out; where it is unsupported the
    // browser falls back to 'true' and the paste handler below does the work.
    el.setAttribute('contenteditable', 'plaintext-only');

    if (el.contentEditable !== 'plaintext-only') el.setAttribute('contenteditable', 'true');

    el.setAttribute('spellcheck', 'false');
    el.setAttribute('role', 'textbox');
    if (kind === 'multiline') el.setAttribute('aria-multiline', 'true');

    let original = readValue(el, kind);

    on(el, 'focus', () => {
        original = readValue(el, kind);
        post({ type: 'select', block: blockOf(el) });
    });

    on(el, 'input', () => commit(el));
    on(el, 'blur', () => commit(el, true));

    on(el, 'paste', event => {
        event.preventDefault();

        const text = event.clipboardData?.getData('text/plain') ?? '';

        document.execCommand('insertText', false, kind === 'multiline' ? text : text.replace(/\s*\n+\s*/g, ' '));
    });

    on(el, 'keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            writeText(el, original);
            commit(el, true);
            el.blur();

            return;
        }

        if (event.key === 'Enter' && kind === 'text') {
            event.preventDefault();
            el.blur();
        }
    });
}

function bindActivatable(el: HTMLElement, kind: FieldKind): void {
    el.setAttribute('data-myra-activatable', '');
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');

    const fire = (event: Event) => {
        event.preventDefault();
        event.stopPropagation();

        const block = blockOf(el);
        const field = el.dataset.myraField ?? '';

        if (block === '' || field === '') return;

        post({ type: 'select', block });
        post({ type: 'activate', block, field, kind });
    };

    on(el, 'click', fire);
    on(el, 'keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') fire(event);
    });
}

/** In edit mode a link must not navigate the frame away from the draft. */
function trapNavigation(): void {
    on(
        document,
        'click',
        event => {
            if (!active) return;

            const anchor = (event.target as Element | null)?.closest?.('a[href]');

            if (anchor) {
                event.preventDefault();

                const block = blockOf(anchor);

                if (block !== '') post({ type: 'select', block });
            }
        },
        { capture: true },
    );

    on(
        document,
        'submit',
        event => {
            if (active) event.preventDefault();
        },
        { capture: true },
    );
}

function bindAll(): void {
    document.querySelectorAll<HTMLElement>(FIELD_SELECTOR).forEach(el => {
        const kind = kindOf(el);

        if (isInlineKind(kind)) {
            bindInline(el, kind);

            return;
        }

        bindActivatable(el, kind);
    });

    // Clicking a section's chrome (not a field) still selects it.
    document.querySelectorAll<HTMLElement>('[data-myra-block]').forEach(section => {
        on(section, 'click', event => {
            if (!active) return;
            if ((event.target as Element | null)?.closest('[data-myra-field]')) return;

            post({ type: 'select', block: section.dataset.myraBlock ?? '' });
        });
    });
}

function enable(): void {
    if (active) return;

    active = true;
    injectStyle();
    document.documentElement.setAttribute('data-myra-live', '');
    bindAll();
    trapNavigation();
}

function disable(): void {
    if (!active) return;

    active = false;
    document.documentElement.removeAttribute('data-myra-live');

    document.querySelectorAll<HTMLElement>(FIELD_SELECTOR).forEach(el => {
        el.removeAttribute('contenteditable');
        el.removeAttribute('data-myra-activatable');
        el.removeAttribute('role');
        el.removeAttribute('tabindex');
        el.removeAttribute('aria-multiline');
    });

    teardown.forEach(fn => {
        try {
            fn();
        } catch {
            // Removing a listener must never break teardown of the rest.
        }
    });

    teardown = [];
}

function highlight(block: string | null): void {
    document.querySelectorAll<HTMLElement>('[data-myra-block]').forEach(el => {
        el.toggleAttribute('data-myra-highlight', block !== null && el.dataset.myraBlock === block);
    });
}

/**
 * Announce readiness and start listening. Safe to call on any page: it returns
 * immediately unless the document is framed.
 */
export function startLiveEditAgent(): () => void {
    if (typeof window === 'undefined' || window.parent === window) return () => {};

    const onMessage = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) return;

        const message = unwrap<HostMessage>(event.data);

        if (message === null) return;

        if (message.type === 'enable') {
            message.on ? enable() : disable();

            return;
        }

        if (message.type === 'highlight') highlight(message.block);
    };

    window.addEventListener('message', onMessage);
    post({ type: 'ready' });

    return () => {
        window.removeEventListener('message', onMessage);
        disable();
    };
}
