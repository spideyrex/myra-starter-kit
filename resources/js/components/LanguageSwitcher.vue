<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Globe, Check } from 'lucide-vue-next';

const { locale } = useI18n();

const currentLocale = computed(() =>
    SUPPORTED_LOCALES.find(l => l.code === locale.value) || SUPPORTED_LOCALES[0]
);

function switchLocale(code: SupportedLocale) {
    setLocale(code);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="size-9">
                <Globe class="size-4" />
                <span class="sr-only">Switch language</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-for="loc in SUPPORTED_LOCALES"
                :key="loc.code"
                class="flex items-center justify-between"
                @click="switchLocale(loc.code)"
            >
                <span class="flex items-center gap-2">
                    <span>{{ loc.flag }}</span>
                    <span>{{ loc.label }}</span>
                </span>
                <Check v-if="locale === loc.code" class="size-4 text-primary" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
