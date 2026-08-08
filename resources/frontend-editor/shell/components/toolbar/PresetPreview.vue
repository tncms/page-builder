<script setup lang="ts">
// Phase 26.1.1 — Reusable proportion-bar preview for a column-layout preset.
// ONE renderer: it turns a preview descriptor (or the preset widths) into a flex
// row of weighted bars via the shared presetPreview helper, so no card or popup
// duplicates SVG/flex sizing logic.
import { computed } from 'vue'
import { previewSegments } from '../../../toolbar/presetPreview'
import type { LayoutPresetPreview } from '../../../../editor/types'

const props = defineProps<{
  widths?: string[]
  preview?: LayoutPresetPreview
}>()

const segments = computed<number[]>(() =>
  previewSegments({ widths: props.widths, preview: props.preview }),
)

function barStyle(weight: number): Record<string, string> {
  return { flexGrow: String(weight), flexBasis: '0' }
}
</script>

<template>
  <span class="pb-live-toolbar__layout-bars" aria-hidden="true">
    <span
      v-for="(weight, i) in segments"
      :key="i"
      class="pb-live-toolbar__layout-bar"
      :style="barStyle(weight)"
    ></span>
  </span>
</template>
