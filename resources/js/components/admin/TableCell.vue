<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ColumnSchema } from '@/types/admin';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Select as UiSelect, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DateCell from '@/components/admin/DateCell.vue';
import ColorSwatch from '@/components/admin/ColorSwatch.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Check, X, Copy } from 'lucide-vue-next';

const props = defineProps<{
    col: ColumnSchema;
    row: any;
}>();

const emit = defineEmits<{
    inline: [payload: { col: ColumnSchema; row: any; value: any }];
}>();

const { can } = usePermissions();

const c = computed(() => props.col as any);
const value = computed(() => props.row[props.col.key]);

const isInlineDisabled = computed(() => {
    if (c.value.permission && !can(c.value.permission)) return true;
    return !!c.value.disabledFn?.(props.row);
});

const rowLabel = computed(() => c.value.rowLabelFn?.(props.row) ?? `${props.col.label} — row ${props.row.id}`);

function push(v: any) {
    emit('inline', { col: props.col, row: props.row, value: v });
}

function formatTextValue(): string {
    const col = c.value;
    if (props.col.type !== 'text') return String(value.value ?? '');

    if (col.formatFn) return col.formatFn(value.value, props.row);

    let result = value.value ?? col.defaultValue ?? '';

    if (col.currency) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: col.currency }).format(num);
        }
    }

    if (col.decimals !== undefined) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) result = num.toFixed(col.decimals);
    }

    result = String(result);

    if (col.limit && result.length > col.limit) {
        result = result.slice(0, col.limit) + '...';
    }

    if (col.prefix) result = col.prefix + result;
    if (col.suffix) result = result + col.suffix;

    return result;
}

const badgeVariant = computed(() => (props.col.type === 'badge' ? (c.value.colors?.[value.value] ?? 'secondary') : 'secondary'));

function copyToClipboard(text: string) {
    navigator.clipboard?.writeText(text);
}
</script>

<template>
    <template v-if="col.type === 'badge'">
        <StatusBadge v-if="Object.keys(c.colors || {}).length === 0" :status="value" />
        <Badge v-else :variant="(badgeVariant as any)">{{ value }}</Badge>
    </template>

    <template v-else-if="col.type === 'date'">
        <DateCell :value="value" :format="c.dateFormat || 'date'" />
    </template>

    <template v-else-if="col.type === 'boolean'">
        <component
            :is="value ? (c.trueIcon || Check) : (c.falseIcon || X)"
            class="size-4"
            :class="value ? (c.trueColor || 'text-success') : (c.falseColor || 'text-muted-foreground')"
            :aria-label="value ? `${col.label}: yes` : `${col.label}: no`"
            role="img"
        />
    </template>

    <template v-else-if="col.type === 'image'">
        <img
            :src="value || c.defaultUrl || ''"
            :class="{ 'rounded-full': c.circular }"
            :style="{ width: `${c.imageSize || 40}px`, height: `${c.imageSize || 40}px` }"
            class="object-cover"
            :alt="col.label"
        />
    </template>

    <template v-else-if="col.type === 'icon'">
        <component
            :is="c.iconFn(value, row)"
            v-if="c.iconFn"
            class="size-5"
            :class="c.colorFn ? c.colorFn(value, row) : ''"
            role="img"
            :aria-label="col.label"
        />
    </template>

    <template v-else-if="col.type === 'color'">
        <ColorSwatch
            :value="value"
            :size="c.swatchSize ?? 16"
            :shape="c.swatchShape ?? 'square'"
            :show-value="c.swatchShowValue !== false"
            :copyable="c.copyable !== false"
            :copy-message="c.copyMessage"
        />
    </template>

    <template v-else-if="col.type === 'toggle'">
        <Switch
            :model-value="!!value"
            :disabled="isInlineDisabled"
            :aria-label="rowLabel"
            @update:model-value="(v: boolean) => push(v)"
        />
    </template>

    <template v-else-if="col.type === 'checkbox'">
        <Checkbox
            :model-value="c.indeterminateFn?.(row) ? 'indeterminate' : !!value"
            :disabled="isInlineDisabled"
            :aria-label="rowLabel"
            @update:model-value="(v: boolean | 'indeterminate') => push(v === 'indeterminate' ? false : !!v)"
        />
    </template>

    <template v-else-if="col.type === 'select'">
        <UiSelect
            :model-value="String(value ?? '')"
            :disabled="isInlineDisabled"
            @update:model-value="(v: any) => push(String(v ?? ''))"
        >
            <SelectTrigger class="h-8 w-[140px]" :aria-label="rowLabel">
                <SelectValue :placeholder="c.placeholder || 'Select...'" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="opt in (c.options || [])" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
            </SelectContent>
        </UiSelect>
    </template>

    <template v-else-if="col.type === 'textinput'">
        <Input
            :model-value="value ?? ''"
            :placeholder="c.placeholder || ''"
            :disabled="isInlineDisabled"
            :aria-label="rowLabel"
            class="h-8 w-[160px]"
            @update:model-value="(v: any) => push(String(v))"
        />
    </template>

    <template v-else>
        <div class="flex items-center gap-1">
            <template v-if="c.urlFn">
                <Link :href="c.urlFn(row)" class="text-primary hover:underline">{{ formatTextValue() }}</Link>
            </template>
            <template v-else>
                <span :class="{ 'whitespace-nowrap': !c.wrap }">{{ formatTextValue() }}</span>
            </template>
            <button
                v-if="c.copyable && value"
                type="button"
                class="text-muted-foreground hover:text-foreground"
                :aria-label="`Copy ${col.label}`"
                @click.stop="copyToClipboard(String(value))"
            >
                <Copy class="size-3" />
            </button>
        </div>
        <p v-if="c.descriptionFn" class="text-xs text-muted-foreground">{{ c.descriptionFn(row) }}</p>
    </template>
</template>
