<script setup lang="ts">
// Phase 26.47F — compatibility status for the Inspector. Rule 8: use the EXISTING
// compatibility metadata; never implement new compatibility logic. Registry
// resources are schema-validated at import, so the derived state is Compatible
// (metadata present) or Unknown (none). The "requires-newer" / "unsupported"
// states render only when the metadata carries an explicit `status` flag (future,
// server-driven) — no version maths is performed here.
import { computed } from 'vue'

const props = defineProps<{ compatibility?: Record<string, unknown> | null }>()

const STATES = {
  compatible: { label: 'Compatible', className: 'is-ok', icon: '✓' },
  'requires-newer': { label: 'Requires newer Builder', className: 'is-warn', icon: '↑' },
  unsupported: { label: 'Unsupported', className: 'is-bad', icon: '✕' },
  unknown: { label: 'Unknown', className: 'is-muted', icon: '?' },
} as const

type StatusKey = keyof typeof STATES

const status = computed<StatusKey>(() => {
  const meta = props.compatibility
  if (!meta || typeof meta !== 'object' || Object.keys(meta).length === 0) return 'unknown'
  const explicit = meta.status
  if (typeof explicit === 'string' && explicit in STATES) return explicit as StatusKey
  return 'compatible'
})

const state = computed(() => STATES[status.value])
</script>

<template>
  <span class="pb-rc" :class="state.className" data-testid="compatibility" :data-status="status">
    <span class="pb-rc__icon" aria-hidden="true">{{ state.icon }}</span>
    <span class="pb-rc__label">{{ state.label }}</span>
  </span>
</template>

<style scoped>
.pb-rc { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; }
.pb-rc__icon {
  display: inline-grid; place-items: center; width: 1.05rem; height: 1.05rem; border-radius: 50%;
  font-size: 0.65rem; border: 1px solid currentColor;
}
.pb-rc.is-ok { color: #7ee0a8; }
.pb-rc.is-warn { color: #ffcf70; }
.pb-rc.is-bad { color: #ff9aa8; }
.pb-rc.is-muted { color: var(--pb-editor-text-muted, #9aa4b2); }
</style>
