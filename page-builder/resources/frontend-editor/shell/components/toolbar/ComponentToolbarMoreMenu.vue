<script setup lang="ts">
// Phase 22 — The "More" dropdown: secondary component actions. Every item maps
// to an existing store command via the toolbar orchestrator; disabled states
// come from the same store getters so nothing dead is offered. Paste items only
// appear when there is something on the clipboard.
import { computed } from 'vue'
import { useToolbarStore } from '../../../toolbar/ToolbarManager'
import ComponentToolbarMenu from './ComponentToolbarMenu.vue'
import type { ActionContext, ToolbarAction } from '../../../toolbar/actionModel'

const toolbar = useToolbarStore()

// Phase 26.1 — the type-aware "more" group (Reverse columns, Toggle wrap, Align,
// Justify, Save as Pattern seam). Rendered from the model, not hardcoded.
const moreActions = computed<ToolbarAction[]>(() =>
  toolbar.resolvedActions.filter((action) => action.group === 'more'),
)

function runAction(action: ToolbarAction): void {
  const ctx: ActionContext = toolbar.actionContext
  if (action.disabled?.(ctx)) return
  void action.run(ctx)
  toolbar.closeMenu()
}
</script>

<template>
  <ComponentToolbarMenu :open="toolbar.menuOpen" @close="toolbar.closeMenu()">
    <template v-if="moreActions.length">
      <button
        v-for="action in moreActions"
        :key="action.id"
        type="button"
        role="menuitem"
        class="pb-live-toolbar__menu-item"
        :data-action-id="action.id"
        :disabled="action.disabled?.(toolbar.actionContext) ?? false"
        @click="runAction(action)"
      >
        <span class="pb-live-toolbar__menu-icon" aria-hidden="true">{{ action.icon }}</span>
        {{ action.label }}
      </button>
      <hr class="pb-live-toolbar__menu-sep" aria-hidden="true" />
    </template>

    <button
      type="button"
      role="menuitem"
      class="pb-live-toolbar__menu-item"
      :disabled="!toolbar.canMoveUp"
      @click="toolbar.moveSelectedUp()"
    >
      <span class="pb-live-toolbar__menu-icon" aria-hidden="true">↑</span> Move up
    </button>
    <button
      type="button"
      role="menuitem"
      class="pb-live-toolbar__menu-item"
      :disabled="!toolbar.canMoveDown"
      @click="toolbar.moveSelectedDown()"
    >
      <span class="pb-live-toolbar__menu-icon" aria-hidden="true">↓</span> Move down
    </button>
    <button
      type="button"
      role="menuitem"
      class="pb-live-toolbar__menu-item"
      :disabled="!toolbar.canSelectParent"
      @click="toolbar.selectParent()"
    >
      <span class="pb-live-toolbar__menu-icon" aria-hidden="true">⤴</span> Select parent
    </button>

    <template v-if="toolbar.hasClipboard">
      <hr class="pb-live-toolbar__menu-sep" aria-hidden="true" />
      <button
        type="button"
        role="menuitem"
        class="pb-live-toolbar__menu-item"
        :disabled="!toolbar.canPasteInside"
        @click="toolbar.pasteInsideSelected()"
      >
        <span class="pb-live-toolbar__menu-icon" aria-hidden="true">⤵</span> Paste inside
      </button>
      <button
        type="button"
        role="menuitem"
        class="pb-live-toolbar__menu-item"
        @click="toolbar.pasteBeforeSelected()"
      >
        <span class="pb-live-toolbar__menu-icon" aria-hidden="true">⇤</span> Paste before
      </button>
      <button
        type="button"
        role="menuitem"
        class="pb-live-toolbar__menu-item"
        @click="toolbar.pasteAfterSelected()"
      >
        <span class="pb-live-toolbar__menu-icon" aria-hidden="true">⇥</span> Paste after
      </button>
    </template>
  </ComponentToolbarMenu>
</template>
