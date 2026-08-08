<script setup lang="ts">
import { computed } from 'vue'
import PbBadge from '../../shared-ui/PbBadge.vue'

// Status semantics (tone + on/off) stay in this consumer; PbBadge stays generic.
// The tint only shows while `on`, mirroring the legacy .pb-editor-badge behaviour.
const props = defineProps<{ label: string; on?: boolean; tone?: 'ok' | 'warn' | 'muted' }>()

const stateClass = computed(() =>
  props.on && props.tone === 'ok'
    ? 'pb-editor-status-badge--on-ok'
    : props.on && props.tone === 'warn'
      ? 'pb-editor-status-badge--on-warn'
      : null,
)
</script>

<template>
  <PbBadge class="pb-editor-status-badge" :class="stateClass">{{ label }}</PbBadge>
</template>
