<script setup lang="ts">
// Phase 20 — Left sidebar with panel tabs: Elements / Navigator / Options.
// Selecting a component on the page auto-switches to Options (Elementor-style).
import { watch } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import { useToolbarStore } from '../../toolbar/ToolbarManager'
import type { SidebarTab } from '../../toolbar/types'
import BuilderElementsPanel from './BuilderElementsPanel.vue'
import BuilderLanguageSwitcher from './BuilderLanguageSwitcher.vue'
import BuilderNavigatorPanel from './BuilderNavigatorPanel.vue'
import BuilderOptionsPanel from './BuilderOptionsPanel.vue'

const store = useEditorStore()
// The active tab lives in the toolbar store so the in-canvas Edit/Options action
// can reveal Options without going through the sidebar (Phase 22).
const toolbar = useToolbarStore()

const TABS: Array<{ id: SidebarTab; label: string; icon: string }> = [
  { id: 'elements', label: 'Elements', icon: '＋' },
  { id: 'navigator', label: 'Navigator', icon: '≣' },
  { id: 'options', label: 'Options', icon: '⚙' },
]

// Selecting a component jumps to its Options; deselecting returns to Elements —
// but only when the user has NOT manually locked a tab (§13). The in-canvas Edit
// action (openOptionsForSelected) clears the lock and forces Options explicitly.
watch(
  () => store.selectedComponentId,
  (id) => {
    if (toolbar.sidebarTabLockedByUser) return
    toolbar.setSidebarTab(id ? 'options' : 'elements')
  },
)
</script>

<template>
  <aside class="pb-live-builder-sidebar">
    <!-- LT-M1 Increment 2D — the ONE editor locale switcher lives here (above the
         panel tabs), not in the top bar. It hides itself unless the translation
         group has more than one locale. -->
    <div class="pb-live-builder-sidebar__langswitch">
      <BuilderLanguageSwitcher />
    </div>

    <nav class="pb-live-builder-panel-tabs" aria-label="Builder panels">
      <button
        v-for="t in TABS"
        :key="t.id"
        type="button"
        class="pb-live-builder-panel-tab"
        :class="{ 'is-active': toolbar.activeSidebarTab === t.id }"
        :aria-selected="toolbar.activeSidebarTab === t.id"
        @click="toolbar.selectSidebarTab(t.id)"
      >
        <span class="pb-live-builder-panel-tab__icon" aria-hidden="true">{{ t.icon }}</span>
        <span class="pb-live-builder-panel-tab__label">{{ t.label }}</span>
      </button>
    </nav>

    <div class="pb-live-builder-sidebar__body">
      <BuilderElementsPanel v-show="toolbar.activeSidebarTab === 'elements'" />
      <BuilderNavigatorPanel v-show="toolbar.activeSidebarTab === 'navigator'" />
      <BuilderOptionsPanel v-show="toolbar.activeSidebarTab === 'options'" />
    </div>
  </aside>
</template>

<style scoped>
/* Spacing for the relocated locale switcher; collapses to nothing when the
   switcher hides itself (single locale / feature off leaves only a comment). */
.pb-live-builder-sidebar__langswitch {
  padding: 0.75rem 0.75rem 0;
}

.pb-live-builder-sidebar__langswitch:empty {
  display: none;
}
</style>
