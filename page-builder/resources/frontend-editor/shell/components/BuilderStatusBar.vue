<script setup lang="ts">
// Phase 20 — Bottom status bar: save state, selected-component breadcrumb, and
// the active device. Read-only; all values come from the shared stores.
import { computed } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import { useShellStore } from '../shellStore'

const store = useEditorStore()
const shell = useShellStore()

// PB-STATE-2V — the label reflects the truthful publication state, not merely the
// local save flag. A saved-but-unpublished draft reads "Unpublished changes" (the
// public page is stale) and a failed publish "Publish failed" — never a reassuring
// "Draft saved" that masks stale public content.
const STATUS_LABELS: Record<string, string> = {
  saving: 'Saving…',
  publishing: 'Publishing…',
  unsaved: 'Unsaved changes',
  publish_failed: 'Publish failed',
  unpublished: 'Unpublished changes',
  published: 'Published',
  none: 'No draft yet',
}

const statusLabel = computed(() => STATUS_LABELS[store.publicationState] ?? 'No draft yet')

const statusTone = computed(() => {
  switch (store.publicationState) {
    case 'saving':
    case 'publishing':
      return 'busy'
    case 'unsaved':
    case 'unpublished':
    case 'publish_failed':
      return 'warn'
    default:
      return 'ok'
  }
})

const breadcrumb = computed(() => {
  const c = store.selectedComponent
  if (!c) return 'Nothing selected'
  return `${c.type} · #${c.id}`
})

const deviceLabel = computed(() => shell.device.charAt(0).toUpperCase() + shell.device.slice(1))
</script>

<template>
  <footer class="pb-live-builder-statusbar">
    <span class="pb-live-builder-statusbar__status" :class="`is-${statusTone}`">
      <span class="pb-live-builder-statusbar__dot" aria-hidden="true"></span>
      {{ statusLabel }}
    </span>

    <span class="pb-live-builder-statusbar__crumb">{{ breadcrumb }}</span>

    <span class="pb-live-builder-statusbar__device">{{ deviceLabel }}</span>
  </footer>
</template>
