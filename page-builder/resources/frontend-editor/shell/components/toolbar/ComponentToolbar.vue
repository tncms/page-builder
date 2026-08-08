<script setup lang="ts">
// Phase 22 — In-canvas component toolbar. A compact floating surface that pins
// beside the selected component in the Live Builder iframe. Position is pure
// geometry: the iframe reports the component rect, we add the iframe screen
// offset and run ToolbarPositioner (above → inside → below → clamp). It renders
// in the shell over the iframe (sibling of the box-model / inline overlays) and
// never touches the public output. Actions delegate to the toolbar store, which
// reuses the existing command bus.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useToolbarStore } from '../../../toolbar/ToolbarManager'
import { useEditorStore } from '../../../../editor/stores/editor'
import { positionToolbar } from '../../../toolbar/ToolbarPositioner'
import ComponentToolbarButton from './ComponentToolbarButton.vue'
import ComponentToolbarBreadcrumb from './ComponentToolbarBreadcrumb.vue'
import ComponentToolbarMoreMenu from './ComponentToolbarMoreMenu.vue'
import LayoutPresetPopup from './LayoutPresetPopup.vue'
import AddElementPicker from './AddElementPicker.vue'
import type { ActionContext, ToolbarAction } from '../../../toolbar/actionModel'

const props = defineProps<{ frameEl: HTMLIFrameElement | null }>()

const toolbar = useToolbarStore()
const editor = useEditorStore()

// The inline (non-More) actions the model resolves for the current selection.
// The template renders whatever this yields — no per-type branching in markup.
const barActions = computed<ToolbarAction[]>(() =>
  toolbar.resolvedActions.filter((action) => action.group !== 'more'),
)

function runAction(action: ToolbarAction): void {
  const ctx: ActionContext = toolbar.actionContext
  if (action.disabled?.(ctx)) return
  void action.run(ctx)
}

const el = ref<HTMLElement | null>(null)
// Seeded with a plausible size for the first paint; re-measured immediately.
const size = ref({ width: 280, height: 40 })
const tick = ref(0)

function measure(): void {
  const node = el.value
  if (!node) return
  const r = node.getBoundingClientRect()
  if (r.width && r.height) size.value = { width: r.width, height: r.height }
}

const pos = computed(() => {
  void tick.value // recompute after scroll/resize (rects aren't reactive)
  const geometry = toolbar.geometry
  const frame = props.frameEl
  if (!toolbar.visible || !geometry || !frame) return null

  const ir = frame.getBoundingClientRect()
  return positionToolbar({
    component: geometry.rect,
    iframe: { top: ir.top, left: ir.left, width: ir.width, height: ir.height },
    viewport: { width: window.innerWidth, height: window.innerHeight },
    toolbar: size.value,
  })
})

const style = computed<Record<string, string>>(() => {
  const p = pos.value
  if (!p) return { display: 'none' }
  return { position: 'fixed', top: `${p.top}px`, left: `${p.left}px` }
})

// Mirror the computed placement into the store (drives the --above/--inside/
// --below modifier class + is observable in tests).
watch(pos, (p) => {
  if (p) toolbar.setPlacement(p.placement)
})

function reposition(): void {
  measure()
  tick.value += 1
}

// Drag handle: the primary "move" affordance. Flush any inline edit, then hand
// off to the existing drag engine. Consume the event so it never doubles as a
// click on the underlying surface.
function onHandleDown(event: PointerEvent): void {
  event.preventDefault()
  event.stopPropagation()
  void toolbar.beginDragSelected(event)
}

const onWindow = () => reposition()

onMounted(() => {
  window.addEventListener('scroll', onWindow, { passive: true })
  window.addEventListener('resize', onWindow)
  void nextTick(measure)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onWindow)
  window.removeEventListener('resize', onWindow)
})

// Re-measure + reposition whenever the toolbar appears or the target changes.
watch(
  () => [toolbar.visible, toolbar.geometry?.componentId],
  () => void nextTick(reposition),
)
</script>

<template>
  <div
    v-if="toolbar.visible"
    ref="el"
    class="pb-live-toolbar"
    :class="`pb-live-toolbar--${toolbar.placement}`"
    :style="style"
    role="toolbar"
    aria-label="Component actions"
    aria-orientation="horizontal"
  >
    <ComponentToolbarBreadcrumb />

    <div class="pb-live-toolbar__bar">
      <span class="pb-live-toolbar__label">
        <span class="pb-live-toolbar__label-name">
          {{ toolbar.label }}
          <span
            v-if="editor.inspectorDirty"
            class="pb-live-toolbar__staged"
            title="Unapplied changes"
            aria-label="Unapplied changes"
          ></span>
        </span>
        <span class="pb-live-toolbar__label-kind">{{ toolbar.kindLabel }}</span>
      </span>

      <div class="pb-live-toolbar__actions">
        <button
          type="button"
          class="pb-live-toolbar__button pb-live-toolbar__handle"
          title="Drag to move"
          aria-label="Drag to move"
          @pointerdown="onHandleDown"
        >
          <span class="pb-live-toolbar__icon" aria-hidden="true">⠿</span>
        </button>

        <ComponentToolbarButton
          v-for="action in barActions"
          :key="action.id"
          :label="action.label"
          :icon="action.icon"
          :danger="action.id === 'delete'"
          :disabled="action.disabled?.(toolbar.actionContext) ?? false"
          @activate="runAction(action)"
        />

        <div class="pb-live-toolbar__more">
          <button
            type="button"
            class="pb-live-toolbar__button"
            title="More actions"
            aria-label="More actions"
            aria-haspopup="menu"
            :aria-expanded="toolbar.menuOpen"
            @click="toolbar.toggleMenu()"
          >
            <span class="pb-live-toolbar__icon" aria-hidden="true">⋯</span>
          </button>
          <ComponentToolbarMoreMenu />
        </div>
      </div>
    </div>

    <LayoutPresetPopup />
    <AddElementPicker />
  </div>
</template>
