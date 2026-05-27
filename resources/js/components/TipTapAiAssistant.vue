<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import type { Editor } from '@tiptap/vue-3';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sparkles,
    Loader2,
    Wand2,
    CheckCheck,
    SpellCheck,
    ArrowDownToLine,
    ArrowUpToLine,
    FileText,
    Palette,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const props = defineProps<{
    editor: Editor;
    disabled?: boolean;
}>();

const loading = ref(false);
const dropdownOpen = ref(false);
const generateOpen = ref(false);
const generatePrompt = ref('');
const promptBox = ref<HTMLElement>();
const promptInput = ref<InstanceType<typeof Input>>();

// Snapshot selection when dropdown opens (editor loses focus on click)
const savedSelection = ref<{ from: number; to: number; text: string } | null>(null);

const hasSelection = computed(() => {
    return !!savedSelection.value?.text;
});

function captureSelection() {
    if (!props.editor) return;
    const { from, to } = props.editor.state.selection;
    if (from !== to) {
        savedSelection.value = {
            from,
            to,
            text: props.editor.state.doc.textBetween(from, to, ' '),
        };
    } else {
        savedSelection.value = null;
    }
}

function openGeneratePrompt() {
    dropdownOpen.value = false;
    nextTick(() => {
        generateOpen.value = true;
        nextTick(() => {
            (promptInput.value?.$el as HTMLInputElement)?.focus();
        });
    });
}

function closeGeneratePrompt() {
    generateOpen.value = false;
    generatePrompt.value = '';
}

function handleClickOutside(e: MouseEvent) {
    if (generateOpen.value && promptBox.value && !promptBox.value.contains(e.target as Node)) {
        closeGeneratePrompt();
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

async function runAction(action: string, options: { prompt?: string; tone?: string } = {}) {
    if (loading.value) return;

    const selection = savedSelection.value;
    const text = selection?.text || '';

    if (action !== 'generate' && !text) {
        toast.error('Please select some text first.');
        return;
    }

    loading.value = true;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        const response = await fetch(route('ai.assist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify({
                action,
                text,
                prompt: options.prompt || '',
                tone: options.tone || '',
            }),
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status}`);
        }

        const reader = response.body?.getReader();
        if (!reader) throw new Error('No response body');

        const decoder = new TextDecoder();
        let accumulated = '';
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed || !trimmed.startsWith('data: ')) continue;

                const payload = trimmed.slice(6);
                if (payload === '[DONE]') continue;

                try {
                    const json = JSON.parse(payload);

                    if (json.error) {
                        throw new Error(json.error);
                    }

                    if (json.content) {
                        accumulated += json.content;
                    }
                } catch (e: any) {
                    if (e.message && !e.message.includes('JSON')) {
                        throw e;
                    }
                }
            }
        }

        if (!accumulated) {
            toast.error('AI returned an empty response.');
            return;
        }

        // Insert or replace content
        if (action === 'generate') {
            props.editor.chain().focus().insertContent(accumulated).run();
        } else if (selection) {
            props.editor
                .chain()
                .focus()
                .deleteRange({ from: selection.from, to: selection.to })
                .insertContent(accumulated)
                .run();
        }

        toast.success('AI content applied.');
    } catch (e: any) {
        toast.error(e.message || 'AI request failed.');
    } finally {
        loading.value = false;
    }
}

function handleGenerate() {
    if (!generatePrompt.value.trim()) return;
    const prompt = generatePrompt.value.trim();
    closeGeneratePrompt();
    runAction('generate', { prompt });
}
</script>

<template>
    <div class="relative" @pointerdown="captureSelection">
        <DropdownMenu v-model:open="dropdownOpen">
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 gap-1.5 px-2"
                    :disabled="disabled || loading"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <Sparkles v-else class="size-4" />
                    <span class="text-xs">AI</span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" class="w-52">
                <DropdownMenuItem @select.prevent="openGeneratePrompt">
                    <Wand2 class="mr-2 size-4" />
                    Generate from prompt...
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem :disabled="!hasSelection" @select="runAction('improve')">
                    <CheckCheck class="mr-2 size-4" />
                    Improve Writing
                </DropdownMenuItem>
                <DropdownMenuItem :disabled="!hasSelection" @select="runAction('fix_grammar')">
                    <SpellCheck class="mr-2 size-4" />
                    Fix Grammar & Spelling
                </DropdownMenuItem>
                <DropdownMenuItem :disabled="!hasSelection" @select="runAction('make_shorter')">
                    <ArrowDownToLine class="mr-2 size-4" />
                    Make Shorter
                </DropdownMenuItem>
                <DropdownMenuItem :disabled="!hasSelection" @select="runAction('make_longer')">
                    <ArrowUpToLine class="mr-2 size-4" />
                    Make Longer
                </DropdownMenuItem>
                <DropdownMenuItem :disabled="!hasSelection" @select="runAction('summarize')">
                    <FileText class="mr-2 size-4" />
                    Summarize
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuSub>
                    <DropdownMenuSubTrigger :disabled="!hasSelection">
                        <Palette class="mr-2 size-4" />
                        Change Tone
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                        <DropdownMenuItem @select="runAction('change_tone', { tone: 'professional' })">
                            Professional
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="runAction('change_tone', { tone: 'casual' })">
                            Casual
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="runAction('change_tone', { tone: 'friendly' })">
                            Friendly
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="runAction('change_tone', { tone: 'formal' })">
                            Formal
                        </DropdownMenuItem>
                    </DropdownMenuSubContent>
                </DropdownMenuSub>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Generate prompt box — standalone div, not inside Radix Popover -->
        <div
            v-if="generateOpen"
            ref="promptBox"
            class="absolute left-0 top-full z-50 mt-1 w-80 rounded-md border bg-popover p-3 shadow-md"
        >
            <p class="text-sm font-medium">Generate from prompt</p>
            <div class="mt-2 flex gap-2">
                <Input
                    ref="promptInput"
                    v-model="generatePrompt"
                    placeholder="Describe what to write..."
                    class="h-8 text-sm"
                    @keydown.enter.prevent="handleGenerate"
                />
                <Button
                    type="button"
                    size="sm"
                    class="h-8 shrink-0"
                    :disabled="!generatePrompt.trim()"
                    @click="handleGenerate"
                >
                    Go
                </Button>
            </div>
        </div>
    </div>
</template>
