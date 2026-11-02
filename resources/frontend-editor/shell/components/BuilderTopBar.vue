<script setup lang="ts">
// Phase 20 — Builder top bar: owner identity, device switcher, and the core
// actions (Undo/Redo, Save Draft, Publish, Structure editor, View, Exit). Reuses
// the structure-editor store for all state + commands.
import { computed, nextTick, ref } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import { appendSelection, guardedNavigate } from '../../../editor/utils/navigation'
import { type EditorMode, type ModeSwitchChoice, runModeSwitch, targetModeFor } from '../modeSwitch'
import { useShellStore } from '../shellStore'
import BuilderDeviceSwitcher from './BuilderDeviceSwitcher.vue'
import BuilderModeSwitchDialog from './BuilderModeSwitchDialog.vue'

const store = useEditorStore()
const shell = useShellStore()

const modeLabel = computed(() => (store.isBuilderMode ? 'Page Builder' : 'Classic'))

// PB-CORE-AUTHORITY-REMEDIATION-R1 — which source renders publicly, switched only
// through the confirmation dialog (the server re-checks rights and staleness).
const t = (key: string, fallback: string): string => store.uiString(key, fallback)
const currentMode = computed<EditorMode>(() => (store.isBuilderMode ? 'builder' : 'classic'))
const canSwitchMode = computed(() => Boolean(store.permissions?.can_switch_mode && store.boot?.api?.mode))
const modeDialogOpen = ref(false)
const modeTrigger = ref<HTMLButtonElement | null>(null)
const modeBusy = ref(false)
const modeError = ref<string | null>(null)

function openModeDialog(): void {
  modeError.value = null
  modeDialogOpen.value = true
}

function closeModeDialog(): void {
  if (modeBusy.value) return
  modeDialogOpen.value = false
  // Focus returns to the control that opened the dialog.
  void nextTick(() => modeTrigger.value?.focus())
}

async function chooseMode(choice: ModeSwitchChoice): Promise<void> {
  const client = store.client
  if (!client || modeBusy.value) return
  modeBusy.value = true
  modeError.value = null

  try {
    const switched = await runModeSwitch(choice, targetModeFor(currentMode.value), {
      save: () => store.saveDraft(),
      stillDirty: () => store.hasUnsavedRisk,
      switchMode: async (target) => {
        store.applyModeSwitch(await client.switchMode(target, store.layout?.publication_token ?? null))
      },
    })

    if (switched) {
      modeDialogOpen.value = false
    } else {
      // The save failed: stay in the dialog with the editor's typed save error.
      modeError.value = store.error ?? t('modeSwitchSaveFailed', 'The draft could not be saved, so nothing was switched.')
    }
  } catch (error) {
    modeError.value = error instanceof Error ? error.message : t('modeSwitchFailed', 'The rendering source could not be changed.')
  } finally {
    modeBusy.value = false
  }
}

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
        v-if="canSwitchMode"
        ref="modeTrigger"
        type="button"
        class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost"
        data-test="mode-switch-open"
        :title="t('modeSwitchButtonTitle', 'Choose whether the classic content or the Page Builder layout renders publicly')"
        @click="openModeDialog"
      >
        {{ currentMode === 'builder' ? t('modeSwitchButtonBuilder', 'Public: Page Builder') : t('modeSwitchButtonClassic', 'Public: Classic') }}
      </button>

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
        :disabled="store.publishing || !!store.publishBlockedReason"
        :title="store.publishBlockedReason ?? undefined"
        data-pb-publish
        @click="store.publish()"
      >
        {{ store.publishing ? 'Publishing…' : 'Publish' }}
      </button>
      <button type="button" class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost" @click="store.exit()">
        Exit
      </button>
    </div>
    <BuilderModeSwitchDialog
      v-if="modeDialogOpen"
      :current="currentMode"
      :target="targetModeFor(currentMode)"
      :dirty="store.hasUnsavedRisk"
      :busy="modeBusy"
      :error="modeError"
      :t="t"
      @cancel="closeModeDialog"
      @choose="chooseMode"
    />
  </header>
</template>
