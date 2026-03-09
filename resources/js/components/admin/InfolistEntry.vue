<script setup lang="ts">
import type { EntrySchema } from '@/composables/useInfolistSchema';
import { Badge } from '@/components/ui/badge';
import DateCell from './DateCell.vue';
import { Check, X, Copy } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const props = defineProps<{
    entry: EntrySchema;
    record: Record<string, any>;
}>();

function getValue() {
    return props.record[props.entry.key];
}

function formatText(): string {
    const entry = props.entry;
    const value = getValue();

    if (entry.formatFn) return entry.formatFn(value, props.record);

    let result = value ?? entry.defaultValue ?? '';

    if (entry.currency) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: entry.currency }).format(num);
        }
    }

    if (entry.decimals !== undefined) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) {
            result = num.toFixed(entry.decimals);
        }
    }

    result = String(result);

    if (entry.limit && result.length > entry.limit) {
        result = result.slice(0, entry.limit) + '...';
    }

    if (entry.prefix) result = entry.prefix + result;
    if (entry.suffix) result = result + entry.suffix;

    return result;
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
    toast.success('Copied to clipboard');
}

function isEntryVisible(): boolean {
    if (props.entry.visibleFn) return props.entry.visibleFn(props.record);
    if (props.entry.hiddenFn) return !props.entry.hiddenFn(props.record);
    if (props.entry.hidden) return false;
    return true;
}
</script>

<template>
    <div
        v-if="isEntryVisible()"
        class="space-y-1"
        :style="entry.colSpan ? `grid-column: span ${entry.colSpan}` : ''"
    >
        <dt class="text-sm font-medium text-muted-foreground" :title="entry.tooltip">
            {{ entry.label }}
        </dt>
        <dd class="text-sm">
            <!-- Text entry -->
            <template v-if="entry.type === 'text'">
                <div class="flex items-center gap-1.5">
                    <template v-if="entry.isBadge">
                        <Badge :variant="(entry.badgeColors?.[getValue()] ?? 'secondary') as any">
                            {{ formatText() }}
                        </Badge>
                    </template>
                    <template v-else-if="entry.urlFn">
                        <a :href="entry.urlFn(record)" class="text-primary hover:underline">
                            {{ formatText() }}
                        </a>
                    </template>
                    <template v-else>
                        <span>{{ formatText() }}</span>
                    </template>
                    <button
                        v-if="entry.copyable && getValue()"
                        class="text-muted-foreground hover:text-foreground"
                        @click="copyToClipboard(String(getValue()))"
                    >
                        <Copy class="size-3" />
                    </button>
                </div>
                <p v-if="entry.descriptionFn" class="mt-0.5 text-xs text-muted-foreground">
                    {{ entry.descriptionFn(record) }}
                </p>
            </template>

            <!-- Badge entry -->
            <template v-else-if="entry.type === 'badge'">
                <Badge :variant="(entry.badgeColors?.[getValue()] ?? 'secondary') as any">
                    {{ getValue() }}
                </Badge>
            </template>

            <!-- Date entry -->
            <template v-else-if="entry.type === 'date'">
                <DateCell :value="getValue()" :format="entry.dateFormat || 'date'" />
            </template>

            <!-- Boolean entry -->
            <template v-else-if="entry.type === 'boolean'">
                <component
                    :is="getValue() ? (entry.trueIcon || Check) : (entry.falseIcon || X)"
                    class="size-5"
                    :class="getValue() ? (entry.trueColor || 'text-success') : (entry.falseColor || 'text-muted-foreground')"
                />
            </template>

            <!-- Image entry -->
            <template v-else-if="entry.type === 'image'">
                <img
                    v-if="getValue()"
                    :src="getValue()"
                    :class="{ 'rounded-full': entry.circular }"
                    :style="{ width: `${entry.imageSize || 64}px`, height: `${entry.imageSize || 64}px` }"
                    class="object-cover rounded"
                    :alt="entry.label"
                />
                <span v-else class="text-muted-foreground">—</span>
            </template>

            <!-- Icon entry -->
            <template v-else-if="entry.type === 'icon'">
                <component
                    v-if="entry.iconFn"
                    :is="entry.iconFn(getValue(), record)"
                    class="size-5"
                    :class="entry.colorFn ? entry.colorFn(getValue(), record) : ''"
                />
            </template>

            <!-- Repeatable entry -->
            <template v-else-if="entry.type === 'repeatable'">
                <div v-if="Array.isArray(getValue()) && getValue().length > 0" class="space-y-2">
                    <div
                        v-for="(item, idx) in getValue()"
                        :key="idx"
                        class="rounded-lg border p-3"
                    >
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <InfolistEntry
                                v-for="sub in (entry.subSchema || [])"
                                :key="sub.key"
                                :entry="sub"
                                :record="item"
                            />
                        </dl>
                    </div>
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </template>

            <!-- Key-Value entry -->
            <template v-else-if="entry.type === 'key-value'">
                <div v-if="getValue() && typeof getValue() === 'object'" class="rounded-lg border">
                    <div
                        v-for="(val, key) in getValue()"
                        :key="String(key)"
                        class="flex items-center justify-between border-b px-3 py-2 text-sm last:border-b-0"
                    >
                        <span class="font-medium">{{ key }}</span>
                        <span class="text-muted-foreground">{{ val }}</span>
                    </div>
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </template>
        </dd>
    </div>
</template>
