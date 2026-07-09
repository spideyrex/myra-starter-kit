<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { onMounted, ref } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

// Persist the sidebar scroll position across Inertia navigations.
// Pages use a per-page layout, so the sidebar (and this scroll container)
// remounts on every visit — without this it would jump back to the top.
const scrollEl = ref<HTMLElement | null>(null)
const STORAGE_KEY = "sidebar:scrollTop"

let rafId = 0
function onScroll() {
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    rafId = 0
    if (scrollEl.value) {
      try {
        sessionStorage.setItem(STORAGE_KEY, String(scrollEl.value.scrollTop))
      } catch {
        /* sessionStorage unavailable — ignore */
      }
    }
  })
}

onMounted(() => {
  try {
    const saved = sessionStorage.getItem(STORAGE_KEY)
    if (scrollEl.value && saved !== null) {
      scrollEl.value.scrollTop = Number(saved)
    }
  } catch {
    /* sessionStorage unavailable — ignore */
  }
})
</script>

<template>
  <div
    ref="scrollEl"
    data-slot="sidebar-content"
    data-sidebar="content"
    :class="cn('flex min-h-0 flex-1 flex-col gap-2 overflow-auto group-data-[collapsible=icon]:overflow-hidden', props.class)"
    @scroll="onScroll"
  >
    <slot />
  </div>
</template>
