<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Kbd } from '@/components/ui/kbd';
import { Skeleton } from '@/components/ui/skeleton';
import { useToast } from '@/composables/useToast';
import type { CodeLanguage } from '@/composables/useFormSchema';
import type { CodeMirrorHandle } from '@/composables/useCodeMirror';
import { Check, Copy } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    fieldId: string;
    label?: string;
    error?: string;
    describedBy?: string;
    disabled?: boolean;
    modelValue?: any;
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
}>(), {
    codeLanguage: 'plaintext',
    codeLineNumbers: true,
    codeWrap: true,
    codeTabSize: 2,
    codeMinHeight: '12rem',
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();
const toast = useToast();

const host = ref<HTMLElement | null>(null);
const handle = ref<CodeMirrorHandle | null>(null);
const ready = ref(false);
const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

const isReadOnly = computed(() => !!props.codeReadOnly || !!props.disabled);

const contentAttributes = computed<Record<string, string>>(() => ({
    role: 'textbox',
    'aria-multiline': 'true',
    'aria-labelledby': `${props.fieldId}-label`,
    ...(props.describedBy ? { 'aria-describedby': props.describedBy } : {}),
    'aria-invalid': props.error ? 'true' : 'false',
    ...(isReadOnly.value ? { 'aria-readonly': 'true' } : {}),
}));

onMounted(async () => {
    if (!host.value) return;
    const { mountEditor } = await import('@/composables/useCodeMirror');
    if (!host.value) return;
    handle.value = await mountEditor({
        parent: host.value,
        doc: String(props.modelValue ?? ''),
        language: props.codeLanguage,
        readOnly: isReadOnly.value,
        lineNumbers: props.codeLineNumbers,
        wrap: props.codeWrap,
        tabSize: props.codeTabSize,
        indentWithTab: props.codeIndentWithTab,
        autocomplete: props.codeAutocomplete,
        contentAttributes: contentAttributes.value,
        onChange: (value: string) => emit('update:modelValue', value),
    });
    ready.value = true;
});

onBeforeUnmount(() => {
    if (copiedTimer) clearTimeout(copiedTimer);
    handle.value?.destroy();
    handle.value = null;
});

watch(() => props.modelValue, v => handle.value?.setDoc(String(v ?? '')));
watch(() => props.codeLanguage, l => handle.value?.setLanguage(l));
watch(isReadOnly, v => handle.value?.setReadOnly(v));
watch(contentAttributes, attrs => handle.value?.setContentAttributes(attrs), { deep: true });

/** clipboard API needs a secure context; execCommand keeps copy alive over plain HTTP. */
async function writeClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // fall through
    }
    try {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    } catch {
        return false;
    }
}

async function copy() {
    const ok = await writeClipboard(String(props.modelValue ?? ''));
    if (!ok) {
        toast.error(t('fields.copyFailed'));
        return;
    }
    copied.value = true;
    toast.success(t('fields.copied'));
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 1500);
}

const showHeader = computed(() => !!props.codeFilename || !!props.codeCopyable || props.codeLanguage !== 'plaintext');
</script>

<template>
    <div
        class="myra-code overflow-hidden rounded-md border bg-[var(--code-bg)]"
        :class="disabled ? 'pointer-events-none opacity-50' : ''"
    >
        <div
            v-if="showHeader"
            class="flex flex-wrap items-center gap-2 border-b bg-muted/60 px-3 py-1.5 text-xs text-muted-foreground"
        >
            <span v-if="codeFilename" class="truncate font-mono font-medium text-foreground">{{ codeFilename }}</span>
            <span v-if="codeLanguage !== 'plaintext'" class="uppercase tracking-wide">{{ codeLanguage }}</span>

            <span class="ml-auto flex items-center gap-2">
                <span class="hidden items-center gap-1 sm:inline-flex">
                    <Kbd>{{ codeIndentWithTab ? 'Esc' : 'Tab' }}</Kbd>
                    <span>{{ codeIndentWithTab ? t('fields.escLeaves') : t('fields.tabMovesFocus') }}</span>
                </span>
                <Button
                    v-if="codeCopyable"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-6 px-2"
                    :aria-label="t('fields.copyCode')"
                    @click="copy"
                >
                    <component :is="copied ? Check : Copy" class="size-3.5" aria-hidden="true" />
                </Button>
            </span>
        </div>

        <div class="relative">
            <Skeleton v-if="!ready" class="w-full rounded-none" :style="{ height: codeMinHeight }" />
            <div
                v-show="ready"
                ref="host"
                class="myra-code-host"
                :style="{ '--cm-min': codeMinHeight, '--cm-max': codeMaxHeight ?? 'none' }"
            />
        </div>

        <span class="sr-only">{{ codeIndentWithTab ? t('fields.escapeHint') : t('fields.tabHint') }}</span>
    </div>
</template>
