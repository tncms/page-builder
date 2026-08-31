<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import { appendSelection, guardedNavigate } from '../utils/navigation'
import { useInsertPanelStore } from '../insert'
import StatusBadge from './StatusBadge.vue'
import PbButton from '../../shared-ui/PbButton.vue'

const store = useEditorStore()
const insert = useInsertPanelStore()

const modeLabel = computed(() => (store.isBuilderMode ? 'Page Builder' : 'Classic'))

// Phase 22.2 — cross-mode navigation (Structure is the active mode here).
const liveUrl = computed(() => store.navLinks?.live ?? null)
const viewUrl = computed(() => store.navLinks?.public ?? null)

function goLive(): void {
  // Carry the current selection across, and guard ANY unsaved risk (dirty, an
  // armed/in-flight autosave, or a failed save) — PB-PROD-2.
  guardedNavigate(appendSelection(liveUrl.value, store.selectedComponentId), {
    dirty: store.hasUnsavedRisk,
  })
}
// PB-STATE-2V — truthful publication status (shared with the Live Builder status
// bar): a saved-but-unpublished draft reads "Unpublished changes", a failed publish
// "Publish failed"; only a draft matching the published snapshot reads "Published".
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

// StatusBadge tints only while `on` and supports ok | warn | muted.
const statusOn = computed(() => {
  const s = store.publicationState
  return s === 'unsaved' || s === 'unpublished' || s === 'publish_failed' || s === 'published'
})
const statusTone = computed<'ok' | 'warn' | 'muted'>(() => {
  switch (store.publicationState) {
    case 'unsaved':
    case 'unpublished':
    case 'publish_failed':
      return 'warn'
    case 'published':
      return 'ok'
    default:
      return 'muted'
  }
})
</script>

<template>
  <header class="pb-editor-topbar">
    <div class="pb-editor-topbar__owner">
      <span class="pb-editor-topbar__title">{{ store.owner?.title ?? 'Untitled' }}</span>
      <span class="pb-editor-topbar__meta">
        {{ store.owner?.label }} #{{ store.owner?.id }}
        <StatusBadge :label="modeLabel" tone="muted" />
      </span>
    </div>

    <div class="pb-editor-topbar__status">
      <StatusBadge :label="statusLabel" :on="statusOn" :tone="statusTone" />
    </div>

    <!-- Phase 22.2 — mode switch (Structure is the active mode). -->
    <nav class="pb-editor-modeswitch" aria-label="Editor mode">
      <span class="pb-editor-modeswitch__item is-active" aria-current="page">Structure Builder</span>
      <a
        v-if="liveUrl"
        class="pb-editor-modeswitch__item"
        :href="liveUrl"
        aria-label="Open Live Builder"
        title="Open the full-screen Live Builder"
        @click.prevent="goLive"
      >
        Live Builder
      </a>
      <a
        v-if="viewUrl"
        class="pb-editor-modeswitch__item"
        :href="viewUrl"
        target="_blank"
        rel="noopener"
        aria-label="View page"
        title="Open the public page in a new tab"
      >
        View Page
      </a>
    </nav>

    <div class="pb-editor-topbar__actions">
      <!-- Insert stays on the .pb-editor-btn--insert compat skin: a pre-existing
           test asserts that selector (not rewritten this phase). Box = .pb-ui-btn. -->
      <button
        type="button"
        class="pb-ui-btn pb-editor-btn pb-editor-btn--insert"
        title="Insert (Ctrl+I)"
        aria-haspopup="dialog"
        :aria-expanded="insert.isOpen"
        @click="insert.open()"
      >
        + Insert
      </button>
      <PbButton emphasis="ghost" title="Undo (Ctrl+Z)" :disabled="!store.canUndo" @click="store.undo()">
        Undo
      </PbButton>
      <PbButton emphasis="ghost" title="Redo (Ctrl+Shift+Z)" :disabled="!store.canRedo" @click="store.redo()">
        Redo
      </PbButton>
      <PbButton :disabled="store.saving || !store.dirty" @click="store.saveDraft()">
        {{ store.saving ? 'Saving…' : 'Save draft' }}
      </PbButton>
      <PbButton
        v-if="store.canPublish"
        tone="primary"
        :disabled="store.publishing"
        @click="store.publish()"
      >
        {{ store.publishing ? 'Publishing…' : 'Publish' }}
      </PbButton>
      <PbButton emphasis="ghost" @click="store.exit()">
        Exit
      </PbButton>
    </div>
  </header>
</template>
