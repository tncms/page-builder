<script setup lang="ts">
// Phase 14.4 — Outline drop zone.
//
// A thin, layout-flow element the outline tree renders between/around cards and
// inside empty containers. It carries the parent + index it represents as data
// attributes so the OutlineDragController can hit-test it via elementFromPoint.
// It is near-invisible at rest and expands to a hittable strip only while a drag
// is active; the zone the pointer is over highlights blue (legal) or red
// (illegal). Purely presentational — it never mutates anything.
import { computed } from 'vue'
import { useEditorStore } from '../../stores/editor'
import { outlineZoneKey } from '../../drag'

const props = withDefaults(defineProps<{ parentId: string | null; index: number; kind?: 'gap' | 'inside' }>(), {
  kind: 'gap',
})

const store = useEditorStore()

const key = computed(() => outlineZoneKey(props.parentId, props.index, props.kind))
const isActive = computed(() => store.dragActive && store.outlineActiveKey === key.value)
</script>

<template>
  <div
    class="pb-outline-dropzone"
    :class="[
      `pb-outline-dropzone--${kind}`,
      {
        'is-dragging': store.dragActive,
        'is-active': isActive,
        'is-valid': isActive && store.outlineTargetValid,
        'is-invalid': isActive && !store.outlineTargetValid,
      },
    ]"
    data-pb-drop-zone
    :data-pb-drop-parent="parentId ?? ''"
    :data-pb-drop-index="index"
    :data-pb-drop-kind="kind"
    aria-hidden="true"
  >
    <span v-if="kind === 'inside'" class="pb-outline-dropzone__hint">Drop here</span>
  </div>
</template>
