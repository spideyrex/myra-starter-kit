<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Copy } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    value: string | number | null;
    size?: number;
    shape?: 'square' | 'circle';
    showValue?: boolean;
    copyable?: boolean;
    copyMessage?: string;
}>(), {
    size: 16,
    shape: 'square',
    showValue: true,
    copyable: false,
});

const { t } = useI18n();

// Only these forms may reach an inline style. Anything else renders as text.
const COLOR_RE = /^(#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})|rgba?\(\s*[\d.\s,%/]+\)|hsla?\(\s*[\d.\s,%/deg]+\))$/i;

const raw = computed(() => String(props.value ?? '').trim());
const safe = computed(() => (COLOR_RE.test(raw.value) ? raw.value : null));

const hasAlpha = computed(() => {
    if (!safe.value) return false;
    if (safe.value.startsWith('#')) return safe.value.length === 5 || safe.value.length === 9;
    return /^(rgba|hsla)\(/i.test(safe.value) || /\/\s*[\d.]+\s*\)$/.test(safe.value);
});

const swatchStyle = computed(() => {
    const style: Record<string, string> = {
        width: `${props.size}px`,
        height: `${props.size}px`,
    };
    if (!safe.value) return style;
    if (hasAlpha.value) {
        // Checkerboard behind the colour so transparency is visible.
        style.backgroundImage = `linear-gradient(${safe.value}, ${safe.value}), repeating-conic-gradient(var(--border) 0% 25%, var(--background) 0% 50%)`;
        style.backgroundSize = '100% 100%, 8px 8px';
    } else {
        style.background = safe.value;
    }
    return style;
});

async function copy() {
    const text = raw.value;
    if (!text) return;
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const el = document.createElement('textarea');
            el.value = text;
            el.setAttribute('readonly', '');
            el.style.position = 'fixed';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        toast.success(props.copyMessage ?? t('common.copiedToClipboard'));
    } catch {
        toast.error(t('common.copyFailed'));
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <span
            v-if="safe"
            class="inline-block shrink-0 ring-1 ring-border"
            :class="shape === 'circle' ? 'rounded-full' : 'rounded-sm'"
            :style="swatchStyle"
            :role="showValue ? undefined : 'img'"
            :aria-hidden="showValue ? 'true' : undefined"
            :aria-label="showValue ? undefined : raw"
        />
        <span v-if="showValue || !safe" class="font-mono text-xs" :class="safe ? '' : 'text-muted-foreground'">
            {{ raw || '—' }}
        </span>
        <button
            v-if="copyable && raw"
            type="button"
            class="rounded-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-label="`Copy ${raw}`"
            @click.stop="copy"
        >
            <Copy class="size-3" />
        </button>
    </div>
</template>
