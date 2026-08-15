<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Check, ChevronsDownUp, ChevronsUpDown, Copy } from 'lucide-vue-next';
import { sanitizeHtml } from '@/composables/useSanitize';
import type { CodeLanguage } from '@/composables/useInfolistSchema';

const props = withDefaults(defineProps<{
    value: any;
    label?: string;
    codeLanguage?: CodeLanguage;
    codeLineNumbers?: boolean;
    codeWrap?: boolean;
    codeMaxLines?: number;
    codeStartLine?: number;
    codeHighlightLines?: number[];
    codeFilename?: string;
    copyable?: boolean;
}>(), {
    codeLanguage: 'plaintext',
    codeLineNumbers: true,
    codeWrap: true,
    codeMaxLines: 400,
    codeStartLine: 1,
    codeHighlightLines: () => [],
    copyable: true,
});

const { t } = useI18n();

const blockId = `myra-code-${Math.random().toString(36).slice(2, 10)}`;

// Objects/arrays are pretty-printed as JSON and the language is forced.
const isStructured = computed(() => props.value !== null && typeof props.value === 'object');

const source = computed(() => {
    const v = props.value;
    if (v === null || v === undefined) return '';
    if (typeof v === 'object') {
        try {
            return JSON.stringify(v, null, 2);
        } catch {
            return String(v);
        }
    }
    return String(v);
});

const language = computed<CodeLanguage>(() => (isStructured.value ? 'json' : props.codeLanguage));

const host = ref<HTMLElement | null>(null);
const started = ref(false);
const loading = ref(false);
const expanded = ref(false);
const lines = ref<string[]>([]);
const truncated = ref(false);
const totalLines = ref(0);
const copied = ref(false);

const highlightSet = computed(() => new Set(props.codeHighlightLines ?? []));
const gutterWidth = computed(() => `${String(props.codeStartLine + Math.max(totalLines.value, 1)).length + 1}ch`);

const regionLabel = computed(() => props.codeFilename || props.label || `${language.value} code`);

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Drop an outer <pre>/<code> wrapper so rows can be built per line. */
function stripWrapper(html: string): string {
    let out = html.replace(/^\s*<pre[^>]*>/i, '').replace(/<\/pre>\s*$/i, '');
    out = out.replace(/^\s*<code[^>]*>/i, '').replace(/<\/code>\s*$/i, '');
    return out;
}

/**
 * Split highlighted HTML on newlines, re-opening any tag that spans the break.
 * Keeps every line an independently renderable fragment so the gutter can be
 * a real element per row (which stays aligned when wrapping is on).
 */
function splitHtmlLines(html: string): string[] {
    const out: string[] = [];
    const openTags: string[] = [];
    const openNames: string[] = [];
    let cur = '';

    for (const match of html.matchAll(/<[^>]*>|[^<]+/g)) {
        const token = match[0];
        if (token.startsWith('<')) {
            if (token.startsWith('</')) {
                openTags.pop();
                openNames.pop();
            } else if (!token.endsWith('/>')) {
                openTags.push(token);
                openNames.push(/^<\s*([a-zA-Z0-9-]+)/.exec(token)?.[1] ?? 'span');
            }
            cur += token;
            continue;
        }
        const parts = token.split('\n');
        for (let i = 0; i < parts.length; i++) {
            if (i > 0) {
                out.push(cur + [...openNames].reverse().map(n => `</${n}>`).join(''));
                cur = openTags.join('');
            }
            cur += parts[i];
        }
    }
    out.push(cur);
    return out;
}

function renderPlain(code: string) {
    const all = code.split('\n');
    totalLines.value = all.length;
    const shown = expanded.value ? all : all.slice(0, props.codeMaxLines);
    truncated.value = shown.length < all.length;
    lines.value = shown.map(escapeHtml);
}

async function highlight() {
    const code = source.value;
    if (!code) {
        lines.value = [];
        totalLines.value = 0;
        truncated.value = false;
        return;
    }
    loading.value = true;
    try {
        // Read-only path only: never pulls EditorView.
        const { highlightToHtml } = await import('@/composables/useCodeMirror');
        const res = await highlightToHtml(
            code,
            language.value,
            expanded.value ? undefined : { maxLines: props.codeMaxLines },
        );
        lines.value = splitHtmlLines(stripWrapper(sanitizeHtml(res.html)));
        truncated.value = res.truncated;
        totalLines.value = res.totalLines;
    } catch {
        renderPlain(code);
    } finally {
        loading.value = false;
    }
}

let observer: IntersectionObserver | null = null;

function start() {
    if (started.value) return;
    started.value = true;
    highlight();
}

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        start();
        return;
    }
    observer = new IntersectionObserver(entries => {
        if (entries.some(e => e.isIntersecting)) {
            observer?.disconnect();
            observer = null;
            start();
        }
    }, { rootMargin: '200px' });
    if (host.value) observer.observe(host.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});

watch([source, language], () => {
    expanded.value = false;
    if (started.value) highlight();
});

function toggleExpand() {
    expanded.value = !expanded.value;
    highlight();
}

async function copy() {
    const text = source.value;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
        toast.success(t('common.copied'));
    } catch {
        toast.error(t('common.copyFailed'));
    }
}
</script>

<template>
    <div ref="host" class="overflow-hidden rounded-lg border bg-muted/40">
        <div class="flex items-center gap-2 border-b bg-muted/60 px-3 py-1.5">
            <span v-if="codeFilename" class="truncate font-mono text-xs font-medium">{{ codeFilename }}</span>
            <span class="rounded bg-background/70 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-muted-foreground">
                {{ language }}
            </span>
            <span class="ml-auto flex items-center gap-1">
                <Button
                    v-if="truncated || expanded"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    :aria-expanded="expanded"
                    :aria-controls="blockId"
                    @click="toggleExpand"
                >
                    <component :is="expanded ? ChevronsDownUp : ChevronsUpDown" class="size-3.5" />
                    {{ expanded ? t('common.showLess') : t('common.showAll', { count: totalLines }) }}
                </Button>
                <Button
                    v-if="copyable && source"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="size-7"
                    :aria-label="t('common.copy')"
                    @click="copy"
                >
                    <component :is="copied ? Check : Copy" class="size-3.5" />
                </Button>
            </span>
        </div>

        <Skeleton v-if="loading && lines.length === 0" class="m-3 h-24" />

        <pre
            v-else-if="lines.length"
            :id="blockId"
            class="m-0 max-h-[30rem] overflow-auto p-0 text-xs leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            tabindex="0"
            role="region"
            :aria-label="regionLabel"
        ><code :class="`language-${language}`" class="block min-w-full font-mono"><span
                v-for="(line, i) in lines"
                :key="i"
                class="flex"
                :class="highlightSet.has(codeStartLine + i) ? 'bg-warning/20' : ''"
            ><span
                v-if="codeLineNumbers"
                aria-hidden="true"
                class="sticky left-0 shrink-0 select-none border-r bg-muted/60 px-2 text-right tabular-nums text-muted-foreground"
                :style="{ minWidth: gutterWidth }"
            >{{ codeStartLine + i }}</span><span
                class="grow px-3"
                :class="codeWrap ? 'whitespace-pre-wrap break-words' : 'whitespace-pre'"
                v-html="line || '&#8203;'"
            /></span></code></pre>

        <p v-else class="px-3 py-2 text-sm text-muted-foreground">—</p>

        <p v-if="truncated && !expanded" class="border-t px-3 py-1.5 text-xs text-muted-foreground">
            {{ t('common.linesTruncated', { shown: lines.length, total: totalLines }) }}
        </p>
    </div>
</template>
