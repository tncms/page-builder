<script setup lang="ts">
// Phase 20 — Builder top bar: owner identity, device switcher, and the core
// actions (Undo/Redo, Save Draft, Publish, Structure editor, View, Exit). Reuses
// the structure-editor store for all state + commands.
import { computed } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import { appendSelection, guardedNavigate } from '../../../editor/utils/navigation'
import { useShellStore } from '../shellStore'
import BuilderDeviceSwitcher from './BuilderDeviceSwitcher.vue'

const store = useEditorStore()
const shell = useShellStore()

const modeLabel = computed(() => (store.isBuilderMode ? 'Page Builder' : 'Classic'))

// Phase 22.2 — cross-mode navigation (Live Builder is the active mode here).
const structureUrl = computed(() => store.navLinks?.structure ?? shell.structureEditUrl)
const viewUrl = computed(() => store.navLinks?.public ?? shell.publicUrl)
// PB-DRAFT-PREVIEW-R1 §12 — "View Page" means the REAL public page. While the
// content entity is unpublished it has no accessible public page, so the action is
// shown disabled with an explanation instead of linking to a misleading 404. This
// is separate from the private iframe document, which always shows the draft.
const publicUrlAvailable = computed(() => shell.publicUrlAvailable)

function goStructure(): void {
  // Carry the current selection across, and guard ANY unsaved risk (PB-PROD-2).
  guardedNavigate(appendSelection(structureUrl.value, store.selectedComponentId), {
    dirty: store.hasUnsavedRisk,
  })
}
</script>

<template>
  <header class="pb-live-builder-topbar">
    <div class="pb-live-builder-topbar__brand">
      <span class="pb-live-builder-topbar__logo" aria-hidden="true">◆</span>
      <div class="pb-live-builder-topbar__owner">
        <span class="pb-live-builder-topbar__title">{{ store.owner?.title ?? 'Untitled' }}</span>
        <span class="pb-live-builder-topbar__meta">{{ store.owner?.label }} · {{ modeLabel }}</span>
      </div>
    </div>

    <div class="pb-live-builder-topbar__center">
      <BuilderDeviceSwitcher />
    </div>

    <div class="pb-live-builder-topbar__actions">
      <button
        type="button"
        class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--icon"
        title="Undo"
        :disabled="!store.canUndo"
        @click="store.undo()"
      >
        ↶
      </button>
      <button
        type="button"
        class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--icon"
        title="Redo"
        :disabled="!store.canRedo"
        @click="store.redo()"
      >
        ↷
      </button>

      <!-- Phase 22.2 — mode switch (Live Builder is the active mode). -->
      <nav class="pb-live-builder-modeswitch" aria-label="Editor mode">
        <a
          v-if="structureUrl"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          :href="structureUrl"
          aria-label="Switch to Structure Builder"
          title="Switch to the Structure Builder"
          @click.prevent="goStructure"
        >
          Structure Builder
        </a>
        <span
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost pb-live-builder-btn--active"
          aria-current="page"
          aria-label="Live Builder (current mode)"
        >
          Live Builder
        </span>
        <a
          v-if="viewUrl && publicUrlAvailable"
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
          :href="viewUrl"
          target="_blank"
          rel="noopener"
          aria-label="View page"
          title="Open the public page in a new tab"
        >
          View Page
        </a>
        <span
          v-else
          class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost pb-live-builder-btn--disabled"
          role="link"
          aria-disabled="true"
          aria-label="View page (available after content publication)"
          title="Available after content publication"
        >
          View Page
        </span>
      </nav>

      <button
        type="button"
        class="pb-ui-btn pb-live-builder-btn"
        :disabled="store.saving || !store.dirty"
        @click="store.saveDraft()"
      >
        {{ store.saving ? 'Saving…' : 'Save' }}
      </button>
      <button
        v-if="store.canPublish"
        type="button"
        class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--primary"
        :disabled="store.publishing"
        @click="store.publish()"
      >
        {{ store.publishing ? 'Publishing…' : 'Publish' }}
      </button>
      <button type="button" class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost" @click="store.exit()">
        Exit
      </button>
    </div>
  </header>
</template>
