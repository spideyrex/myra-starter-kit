import type { CodeLanguage } from './useFormSchema';
import type { LanguageSupport } from '@codemirror/language';

/**
 * One grammar registry. Vite emits one lazy chunk per arrow, so a form only
 * downloads the language it actually renders.
 */
export const GRAMMARS: Record<Exclude<CodeLanguage, 'plaintext'>, () => Promise<LanguageSupport>> = {
    javascript: () => import('@codemirror/lang-javascript').then(m => m.javascript({ jsx: true })),
    typescript: () => import('@codemirror/lang-javascript').then(m => m.javascript({ typescript: true, jsx: true })),
    vue: () => import('@codemirror/lang-vue').then(m => m.vue()),
    json: () => import('@codemirror/lang-json').then(m => m.json()),
    html: () => import('@codemirror/lang-html').then(m => m.html()),
    css: () => import('@codemirror/lang-css').then(m => m.css()),
    php: () => import('@codemirror/lang-php').then(m => m.php()),
    sql: () => import('@codemirror/lang-sql').then(m => m.sql()),
    markdown: () => import('@codemirror/lang-markdown').then(m => m.markdown()),
    yaml: () => import('@codemirror/lang-yaml').then(m => m.yaml()),
    xml: () => import('@codemirror/lang-xml').then(m => m.xml()),
    python: () => import('@codemirror/lang-python').then(m => m.python()),
    bash: async () => {
        const [{ StreamLanguage, LanguageSupport: LS }, { shell }] = await Promise.all([
            import('@codemirror/language'),
            import('@codemirror/legacy-modes/mode/shell'),
        ]);
        return new LS(StreamLanguage.define(shell));
    },
};

export const CODE_LANGUAGES: CodeLanguage[] = ['plaintext', ...(Object.keys(GRAMMARS) as CodeLanguage[])];

export interface MountOptions {
    parent: HTMLElement;
    doc: string;
    language: CodeLanguage;
    readOnly?: boolean;
    lineNumbers?: boolean;
    wrap?: boolean;
    tabSize?: number;
    indentWithTab?: boolean;
    autocomplete?: boolean;
    /** aria-* written onto the contenteditable — CodeMirror's editor is not labelable. */
    contentAttributes: Record<string, string>;
    onChange: (value: string) => void;
}

export interface CodeMirrorHandle {
    setDoc(value: string): void;
    setLanguage(lang: CodeLanguage): Promise<void>;
    setReadOnly(value: boolean): void;
    setContentAttributes(attrs: Record<string, string>): void;
    focus(): void;
    destroy(): void;
}

async function loadLanguage(lang: CodeLanguage): Promise<LanguageSupport | null> {
    if (lang === 'plaintext') return null;
    const loader = GRAMMARS[lang];
    if (!loader) return null;
    try {
        return await loader();
    } catch {
        return null;
    }
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/**
 * EDITOR path. Pulls @codemirror/{state,view,language,commands}.
 * The theme references only `var(--code-*)` custom properties, so a light/dark
 * or colour-preset switch repaints without a rebuild or a re-highlight.
 */
export async function mountEditor(opts: MountOptions): Promise<CodeMirrorHandle> {
    const [state, view, language, commands, highlight] = await Promise.all([
        import('@codemirror/state'),
        import('@codemirror/view'),
        import('@codemirror/language'),
        import('@codemirror/commands'),
        import('@lezer/highlight'),
    ]);

    const { EditorState, Compartment } = state;
    const { EditorView, keymap, lineNumbers, highlightActiveLine, drawSelection } = view;
    const { HighlightStyle, syntaxHighlighting, bracketMatching, indentUnit } = language;
    const { defaultKeymap, history, historyKeymap, indentWithTab } = commands;
    const t = highlight.tags;

    const myraHighlightStyle = HighlightStyle.define([
        { tag: [t.keyword, t.controlKeyword, t.moduleKeyword, t.operatorKeyword], color: 'var(--code-keyword)' },
        { tag: [t.string, t.special(t.string), t.regexp], color: 'var(--code-string)' },
        { tag: [t.number, t.bool, t.null, t.atom], color: 'var(--code-number)' },
        { tag: [t.comment, t.lineComment, t.blockComment, t.docComment], color: 'var(--code-comment)', fontStyle: 'italic' },
        { tag: [t.function(t.variableName), t.function(t.propertyName), t.labelName], color: 'var(--code-function)' },
        { tag: [t.variableName, t.propertyName, t.attributeName], color: 'var(--code-variable)' },
        { tag: [t.typeName, t.className, t.tagName, t.namespace], color: 'var(--code-type)' },
        { tag: [t.punctuation, t.separator, t.bracket, t.operator], color: 'var(--code-punct)' },
        { tag: [t.link, t.url], color: 'var(--code-function)', textDecoration: 'underline' },
        { tag: t.heading, color: 'var(--code-keyword)', fontWeight: 'bold' },
        { tag: t.invalid, color: 'var(--code-invalid)' },
    ]);

    const myraTheme = EditorView.theme({
        '&': {
            color: 'var(--code-fg)',
            backgroundColor: 'var(--code-bg)',
            fontSize: '0.8125rem',
        },
        '.cm-content': {
            caretColor: 'var(--code-fg)',
            fontFamily: 'var(--code-font, ui-monospace, SFMono-Regular, Menlo, Consolas, monospace)',
            padding: '0.5rem 0',
        },
        '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--code-fg)' },
        '&.cm-focused': { outline: '2px solid var(--ring)', outlineOffset: '-2px' },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
            backgroundColor: 'var(--code-selection)',
        },
        '.cm-activeLine': { backgroundColor: 'var(--code-active-line)' },
        '.cm-gutters': {
            backgroundColor: 'var(--code-bg)',
            color: 'var(--code-gutter-fg)',
            border: 'none',
            borderRight: '1px solid var(--border)',
        },
        '.cm-activeLineGutter': { backgroundColor: 'var(--code-active-line)' },
        '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': {
            outline: '1px solid var(--code-punct)',
            backgroundColor: 'transparent',
        },
    });

    const languageCompartment = new Compartment();
    const readOnlyCompartment = new Compartment();
    const tabSizeCompartment = new Compartment();
    const attributesCompartment = new Compartment();

    const keys = [...defaultKeymap, ...historyKeymap];
    if (opts.indentWithTab) {
        keys.unshift(indentWithTab);
        // A Tab-capturing field with no way out is a WCAG 2.1.2 trap.
        keys.unshift({
            key: 'Escape',
            run: (v: any) => {
                (v.contentDOM as HTMLElement).blur();
                return true;
            },
        });
    }

    const extensions: any[] = [
        history(),
        drawSelection(),
        highlightActiveLine(),
        bracketMatching(),
        syntaxHighlighting(myraHighlightStyle),
        keymap.of(keys),
        myraTheme,
        languageCompartment.of([]),
        readOnlyCompartment.of(EditorState.readOnly.of(!!opts.readOnly)),
        tabSizeCompartment.of(EditorState.tabSize.of(opts.tabSize ?? 2)),
        indentUnit.of(' '.repeat(opts.tabSize ?? 2)),
        attributesCompartment.of(EditorView.contentAttributes.of(opts.contentAttributes)),
        EditorView.updateListener.of(u => {
            if (u.docChanged) opts.onChange(u.state.doc.toString());
        }),
    ];

    if (opts.lineNumbers !== false) extensions.push(lineNumbers());
    if (opts.wrap !== false) extensions.push(EditorView.lineWrapping);

    if (opts.autocomplete) {
        const ac = await import('@codemirror/autocomplete');
        extensions.push(ac.closeBrackets(), ac.autocompletion(), keymap.of([...ac.closeBracketsKeymap, ...ac.completionKeymap]));
    }

    const editor = new EditorView({
        state: EditorState.create({ doc: opts.doc, extensions }),
        parent: opts.parent,
    });

    const applyLanguage = async (lang: CodeLanguage) => {
        const support = await loadLanguage(lang);
        editor.dispatch({ effects: languageCompartment.reconfigure(support ? support : []) });
    };

    await applyLanguage(opts.language);

    return {
        setDoc(value: string) {
            if (value === editor.state.doc.toString()) return;
            editor.dispatch({ changes: { from: 0, to: editor.state.doc.length, insert: value } });
        },
        setLanguage: applyLanguage,
        setReadOnly(value: boolean) {
            editor.dispatch({ effects: readOnlyCompartment.reconfigure(EditorState.readOnly.of(value)) });
        },
        setContentAttributes(attrs: Record<string, string>) {
            editor.dispatch({ effects: attributesCompartment.reconfigure(EditorView.contentAttributes.of(attrs)) });
        },
        focus() {
            editor.focus();
        },
        destroy() {
            editor.destroy();
        },
    };
}

/**
 * READ-ONLY path. Pulls @codemirror/language + @lezer/highlight only — no
 * EditorView, no contenteditable, no keymap. Output carries `tok-*` classes
 * styled from the same `--code-*` tokens (see resources/css/app.css).
 * Callers MUST pass the result through sanitizeHtml before v-html.
 */
export async function highlightToHtml(
    code: string,
    language: CodeLanguage,
    opts?: { maxLines?: number; startLine?: number; highlightLines?: number[] },
): Promise<{ html: string; truncated: boolean; totalLines: number }> {
    const text = String(code ?? '');
    const lines = text.split('\n');
    const totalLines = lines.length;
    const max = opts?.maxLines;
    const truncated = max != null && totalLines > max;
    const source = truncated ? lines.slice(0, max).join('\n') : text;

    const support = await loadLanguage(language);
    if (!support) {
        return { html: escapeHtml(source), truncated, totalLines };
    }

    const { highlightTree, classHighlighter } = await import('@lezer/highlight');
    const tree = support.language.parser.parse(source);

    let html = '';
    let pos = 0;
    highlightTree(tree, classHighlighter, (from, to, classes) => {
        if (from > pos) html += escapeHtml(source.slice(pos, from));
        html += `<span class="${classes}">${escapeHtml(source.slice(from, to))}</span>`;
        pos = to;
    });
    html += escapeHtml(source.slice(pos));

    return { html, truncated, totalLines };
}
