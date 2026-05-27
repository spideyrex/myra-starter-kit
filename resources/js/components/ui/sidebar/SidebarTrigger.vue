<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useSlots } from "vue"
import { ViewVerticalIcon } from '@radix-icons/vue'
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const slots = useSlots()
const { toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    :size="slots.default ? 'default' : 'icon'"
    :class="cn(slots.default ? 'h-auto' : 'h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <slot>
      <ViewVerticalIcon />
      <span class="sr-only">Toggle Sidebar</span>
    </slot>
  </Button>
</template>
