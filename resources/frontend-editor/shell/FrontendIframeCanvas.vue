<script setup lang="ts">
// Phase 16 — Iframe canvas. Embeds the owner's real public page and drives it
// over the postMessage bridge:
//   - iframe -> shell: click/hover/contextmenu select components in the store
//   - shell -> iframe: committed payload edits re-render the preview (no reload),
//     selection changes push a highlight, device changes resize the iframe.
//
// The draft is previewed by rendering the CURRENT (possibly unsaved) payload
// through the editor preview endpoint and swapping it into the iframe canvas —
// nothing is written to the database until Save Draft / Publish.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useEditorStore } from '../../editor/stores/editor'
import { useShellStore } from './shellStore'
import { useOverlayViewport } from '../geometry/useOverlayViewport'
import { iframePointToShellPoint } from '../geometry/IframeCoordinates'
import { ShellBridge } from '../bridge/ShellBridge'
import { handleIframeMessage } from './handlers'
import { buildPatchMessages } from './buildPatch'
import { useInlineStore } from '../inline/InlineManager'
import OverlayInput from '../inline/OverlayInput.vue'
import { useBoxModelStore } from '../box-model/BoxModelManager'
import BoxModelOverlay from '../box-model/BoxModelOverlay.vue'
import { useToolbarStore } from '../toolbar/ToolbarManager'
import { useLayoutHoverPreview } from '../toolbar/layoutHoverPreview'
import ComponentToolbar from './components/toolbar/ComponentToolbar.vue'
import { useLiveDragStore } from '../drag/LiveDragManager'
import LiveDragLayer from './components/drag/LiveDragLayer.vue'
import type { InlineRect, InlineTarget } from '../inline/SelectionManager'
import type { BoxMetrics } from '../box-model/types'
import type { ComponentGeometry } from '../toolbar/types'
import type { LiveDropResolution } from '../drag/types'
import type { BridgeMessage } from '../bridge/messages'
import { PB_BRIDGE_PROTOCOL_VERSION } from '../bridge/messages'
import type { LivePatch } from '../../editor/types'

const store = useEditorStore()
const shell = useShellStore()
const inline = useInlineStore()
const box = useBoxModelStore()
const toolbar = useToolbarStore()
const liveDrag = useLiveDragStore()
const layoutHover = useLayoutHoverPreview()

const iframeEl = ref<HTMLIFrameElement | null>(null)
const canvasEl = ref<HTMLElement | null>(null)
let bridge: ShellBridge | null = null

// HOTFIX — the single coordinate authority. This tracks the iframe's screen rect
// so the `.pb-fe-overlay-viewport` host can sit exactly over the preview; the
// clipped overlays inside it then use iframe-viewport rects verbatim (host-local).
// On any real change we also poke the iframe to re-report its component geometry,
// covering the rare case where the frame MOVES without resizing.
const overlayViewport = useOverlayViewport(
  iframeEl,
  () => [iframeEl.value, canvasEl.value],
  () => bridge?.send('pb:refresh', {}),
)
const overlayViewportStyle = computed<Record<string, string>>(() => ({
  top: `${overlayViewport.rect.value.top}px`,
  left: `${overlayViewport.rect.value.left}px`,
  width: `${overlayViewport.rect.value.width}px`,
  height: `${overlayViewport.rect.value.height}px`,
}))
let previewTimer: number | null = null
const PREVIEW_DEBOUNCE_MS = 350

// Hotfix — readiness handshake state. `readyHandled` makes the ready path
// idempotent (the iframe may announce readiness more than once); `readyTimer`
// backs a bounded fallback so loading can never hang forever.
let readyTimer: number | null = null
let readyHandled = false
const READY_TIMEOUT_MS = 9000

// DEV-only lifecycle diagnostics (stripped from the production build).
function dev(...args: unknown[]): void {
  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.debug('[pb-live-builder]', ...args)
  }
}

// Phase 18 — route the inline-edit messages the iframe sends to the inline store.
// Returns true when it handled the message (so it isn't double-dispatched).
function routeInline(message: BridgeMessage): boolean {
  if (message.type === 'pb:inline-start') {
    inline.open(message.payload as InlineTarget)
    return true
  }
  if (message.type === 'pb:inline-rect') {
    const rect = (message.payload as { rect?: InlineRect })?.rect
    if (rect) inline.updateRect(rect)
    return true
  }
  if (message.type === 'pb:box-model') {
    box.setMetrics(message.payload as BoxMetrics)
    return true
  }
  // Phase 22 — the iframe reports the selected component's rect for the toolbar.
  if (message.type === 'pb:component-geometry') {
    toolbar.setGeometry(message.payload as ComponentGeometry)
    return true
  }
  // Phase 22.1 — cross-iframe drag messages from the iframe.
  return routeDrag(message)
}

// Phase 22.1 — route the iframe's drag lifecycle into the live drag store.
function routeDrag(message: BridgeMessage): boolean {
  switch (message.type) {
    case 'pb:drag-pointer': {
      const p = message.payload as { x?: number; y?: number }
      // The ghost lives in shell space and tracks the pointer across the whole
      // shell, so convert the iframe-viewport point once via the shared helper.
      const s = iframePointToShellPoint(iframeEl.value, { x: p?.x ?? 0, y: p?.y ?? 0 })
      liveDrag.updatePointer(s.x, s.y)
      return true
    }
    case 'pb:drag-target':
      liveDrag.setResolution(message.payload as LiveDropResolution)
      return true
    case 'pb:drag-leave':
      liveDrag.clearResolution()
      return true
    case 'pb:drag-drop':
      void liveDrag.drop(message.payload as LiveDropResolution)
      return true
    case 'pb:drag-cancelled':
      liveDrag.cancel()
      return true
    default:
      return false
  }
}

// Phase 17 — mirror pre-commit inspector edits into the iframe instantly. The
// commit path (deep watch below) still runs on blur/Save and reconciles with the
// authoritative full render, replacing these live patches.
function onLivePatch(patch: LivePatch): void {
  for (const message of buildPatchMessages(patch, store.metadata)) {
    bridge?.send(message.type, message.payload)
  }
}

async function pushRender(): Promise<void> {
  if (!store.client || !store.owner) return
  await store.refreshPreview()
  bridge?.send('pb:update-payload', {
    html: store.previewHtml,
    selectedId: store.selectedComponentId,
  })
}

// Phase 21 — Live Component Runtime. Reconcile a single committed component by
// rendering only it through the server and replacing its iframe marker in place
// — no whole-canvas swap. Falls back to a full preview refresh when the server
// render fails (auth/network) or the iframe can't apply it (structural change).
async function reconcileComponent(componentId: string): Promise<void> {
  const ok = await store.renderComponentPreview(componentId)
  const reconcile = store.componentReconcile
  if (!ok || !reconcile || reconcile.componentId !== componentId) {
    schedulePush(0)
    return
  }
  bridge?.send('pb:replace-component', {
    componentId: reconcile.componentId,
    html: reconcile.html,
    selectedId: store.selectedComponentId,
  })
}

function schedulePush(delay = PREVIEW_DEBOUNCE_MS): void {
  if (previewTimer !== null) window.clearTimeout(previewTimer)
  previewTimer = window.setTimeout(() => {
    previewTimer = null
    void pushRender()
  }, delay)
}

// Ask the (already-loaded) iframe to (re)announce readiness. Safe to call
// repeatedly — the iframe answers each handshake with a fresh pb:ready.
function sendHandshake(): void {
  dev('→ pb:handshake', { origin: shell.frontendOrigin })
  bridge?.send('pb:handshake', {})
}

function clearReadyTimeout(): void {
  if (readyTimer !== null) {
    window.clearTimeout(readyTimer)
    readyTimer = null
  }
}

function startReadyTimeout(): void {
  clearReadyTimeout()
  readyTimer = window.setTimeout(() => {
    readyTimer = null
    if (!shell.iframeReady) {
      const reason = diagnoseTimeout()
      dev('ready timeout — editor bridge did not initialize', reason)
      shell.setIframeTimedOut(true, reason)
    }
  }, READY_TIMEOUT_MS)
}

// Turn a silent timeout into an accurate diagnosis using the iframe's same-origin
// readiness marker. If the marker is present the iframe script DID load and install
// its listener, so the failure is our handshake being rejected (usually an origin
// mismatch). If it is absent (or unreadable) the editor script never booted — the
// common cause is a stale cached bundle — for which the generic copy is accurate.
function diagnoseTimeout(): string {
  try {
    const win = iframeEl.value?.contentWindow as
      | (Window & { __TN_PB_IFRAME_BRIDGE_READY__?: boolean })
      | null
      | undefined
    if (win?.__TN_PB_IFRAME_BRIDGE_READY__ === true) {
      return 'Preview loaded and the editor bridge started, but its messages were rejected — check the editor and preview share the same origin.'
    }
  } catch {
    // Cross-origin: the iframe navigated off our origin (e.g. a login/canonical
    // redirect). Fall through to the generic message.
  }
  return 'Preview loaded, but the editor bridge did not initialize.'
}

// Terminal recovery for a protocol/asset mismatch: only a full reload (which
// re-fetches the freshly cache-busted bundles) can help — Retry cannot.
function hardReload(): void {
  window.location.reload()
}

// The single readiness entry point: clears the loading overlay, pushes the
// current (possibly unsaved) payload once, and tells the iframe its device.
function handleReady(): void {
  if (readyHandled) {
    dev('readiness signal (duplicate, ignored)')
    return
  }
  readyHandled = true
  clearReadyTimeout()
  dev('ready confirmed → clearing loading + pushing render')
  shell.setIframeReady(true)
  bridge?.send('pb:set-device', { device: shell.device })
  void pushRender()
}

// Retry after a timeout: re-arm and re-handshake the live iframe.
function retryHandshake(): void {
  dev('retry handshake')
  readyHandled = false
  shell.resetIframeState()
  startReadyTimeout()
  sendHandshake()
}

function onIframeLoad(): void {
  const el = iframeEl.value
  if (!el) return

  readyHandled = false
  shell.resetIframeState()

  bridge?.stop()
  bridge = new ShellBridge(el, shell.frontendOrigin, (message) => {
    if (routeInline(message)) return
    // Phase 21 — a failed component replacement falls back to a full refresh.
    if (message.type === 'pb:component-replaced') {
      if ((message.payload as { ok?: unknown })?.ok === false) schedulePush(0)
      return
    }
    // Hotfix — the iframe confirming it applied the initial payload is also a
    // valid readiness signal (belt-and-suspenders alongside pb:ready).
    if (message.type === 'pb:rendered') {
      handleReady()
      return
    }
    // Hotfix — protocol guard. A stale cached iframe bundle can still answer the
    // handshake, but tags pb:ready with a DIFFERENT bridge protocol version. That
    // is a terminal "assets out of date" state: Retry cannot reconcile mismatched
    // bundles, so surface it distinctly instead of clearing the overlay against an
    // incompatible runtime.
    if (message.type === 'pb:ready') {
      const reported = (message.payload as { protocol?: unknown })?.protocol
      if (typeof reported === 'number' && reported !== PB_BRIDGE_PROTOCOL_VERSION) {
        dev('protocol mismatch', { iframe: reported, shell: PB_BRIDGE_PROTOCOL_VERSION })
        clearReadyTimeout()
        shell.setProtocolMismatch(true)
        return
      }
      handleReady()
      return
    }
    handleIframeMessage(store, message, {
      onReady: handleReady,
      // Phase 26.3.10 — "+ Add Element" in an empty column opens the component
      // picker anchored to the column's toolbar; the column is already selected
      // (handlers.ts selects it first), so the pick targets it.
      onAddElement: () => toolbar.openAddElementPicker(),
      // Phase 26.2 — a committed visual column resize runs exactly one
      // apply-column-layout command (one undo, then a reconcile re-render).
      onResizeCommit: (containerId, widths) => {
        void store.applyColumnLayout(containerId, widths)
      },
    })
  })
  // Attach the listener BEFORE requesting readiness so the handshake response
  // (pb:ready) can never be dropped the way the iframe's boot-time pb:ready was.
  bridge.start()
  dev('iframe @load — bridge started', { src: el.getAttribute('src') })
  sendHandshake()
  startReadyTimeout()

  // The inline + box-model overlays stream edits back through this bridge.
  inline.bindSender((type, payload) => bridge?.send(type, payload))
  box.bindSender((type, payload) => bridge?.send(type, payload))
  // Phase 26.1.1 — the Layout popup previews column re-proportioning through this
  // same live-patch bridge (send on hover, restore on leave/close/apply).
  layoutHover.bindSender((type, payload) => bridge?.send(type, payload))
  // Phase 22.1 — the live drag store drives the iframe drag session.
  liveDrag.bindSender((type, payload) =>
    bridge?.send(type as Parameters<NonNullable<typeof bridge>['send']>[0], payload),
  )
}

// Phase 22.1 — while a live drag is active, also listen on the shell window so a
// pointer over the shell chrome (outside the iframe) still moves the ghost and a
// release/Escape there cancels cleanly. Over the iframe these never fire (events
// are dispatched in the iframe document), so the two halves never conflict.
function onWindowDragMove(event: PointerEvent): void {
  liveDrag.updatePointer(event.clientX, event.clientY)
  liveDrag.clearResolution()
}
function onWindowDragUp(): void {
  if (liveDrag.dragging) liveDrag.cancel()
}
function onWindowDragKey(event: KeyboardEvent): void {
  if (event.key === 'Escape' && liveDrag.dragging) liveDrag.cancel()
}

const inlineFlush = () => inline.flush()

onMounted(() => {
  store.registerLivePatch(onLivePatch)
  // Save Draft / Publish commit any open inline edit first (Phase 18).
  store.registerInlineFlush(inlineFlush)

  // Re-render preview after any committed payload change. A single-component
  // data edit (Phase 21) reconciles just that component; structural changes
  // (add/move/delete/undo — no directive) still swap the whole canvas.
  watch(
    () => store.currentPayload,
    () => {
      const componentId = store.consumeReconcileDirective()
      if (componentId) {
        void reconcileComponent(componentId)
      } else {
        schedulePush()
      }
    },
    { deep: true },
  )

  // Mirror selection into the iframe overlay, and commit an open inline edit when
  // the selection moves to another component.
  watch(
    () => store.selectedComponentId,
    (id, prev) => {
      if (id !== prev) void inline.flush()
      bridge?.send('pb:select-component', { id })
    },
  )

  // Starting a drag commits any open inline edit.
  watch(
    () => store.isDragging,
    (dragging) => {
      if (dragging) void inline.flush()
    },
  )

  // Phase 22.1 — attach shell-window drag listeners only while a live drag runs.
  watch(
    () => liveDrag.dragging,
    (active) => {
      if (active) {
        window.addEventListener('pointermove', onWindowDragMove, true)
        window.addEventListener('pointerup', onWindowDragUp, true)
        window.addEventListener('keydown', onWindowDragKey, true)
      } else {
        window.removeEventListener('pointermove', onWindowDragMove, true)
        window.removeEventListener('pointerup', onWindowDragUp, true)
        window.removeEventListener('keydown', onWindowDragKey, true)
      }
    },
  )

  // Device changes: resize handled by the bound style; tell the iframe to
  // re-measure its overlays.
  watch(
    () => shell.device,
    () => bridge?.send('pb:set-device', { device: shell.device }),
  )
})

onBeforeUnmount(() => {
  store.unregisterLivePatch(onLivePatch)
  store.unregisterInlineFlush(inlineFlush)
  if (previewTimer !== null) window.clearTimeout(previewTimer)
  clearReadyTimeout()
  window.removeEventListener('pointermove', onWindowDragMove, true)
  window.removeEventListener('pointerup', onWindowDragUp, true)
  window.removeEventListener('keydown', onWindowDragKey, true)
  bridge?.stop()
  bridge = null
})
</script>

<template>
  <div ref="canvasEl" class="pb-fe-canvas">
    <div
      v-if="!shell.iframeReady && !shell.iframeTimedOut && !shell.iframeProtocolMismatch"
      class="pb-fe-canvas__loading"
    >
      Loading preview…
    </div>
    <div
      v-else-if="shell.iframeProtocolMismatch"
      class="pb-fe-canvas__loading pb-fe-canvas__loading--error"
      role="alert"
    >
      <p class="pb-fe-canvas__loading-text">
        Editor assets are out of date. Hard refresh or clear your browser cache to load the latest page-builder bundle.
      </p>
      <button type="button" class="pb-fe-canvas__reload" @click="hardReload">Hard refresh</button>
    </div>
    <div
      v-else-if="!shell.iframeReady && shell.iframeTimedOut"
      class="pb-fe-canvas__loading pb-fe-canvas__loading--error"
      role="alert"
    >
      <p class="pb-fe-canvas__loading-text">
        {{ shell.timeoutReason || 'Preview loaded, but the editor bridge did not initialize.' }}
      </p>
      <button type="button" class="pb-fe-canvas__retry" @click="retryHandshake">Retry</button>
    </div>
    <div class="pb-fe-canvas__frame" :style="{ width: shell.iframeWidth }">
      <iframe
        ref="iframeEl"
        class="pb-fe-canvas__iframe"
        :src="shell.iframeUrl"
        title="Frontend preview"
        @load="onIframeLoad"
      ></iframe>
    </div>

    <!-- HOTFIX — overlay viewport host: glued to the iframe's visible rect and
         `overflow: hidden`, so the overlays inside use host-local coordinates and
         can never paint under the sidebar or outside the preview. -->
    <div class="pb-fe-overlay-viewport" :style="overlayViewportStyle" aria-hidden="true">
      <!-- Box model overlay (spacing handles) for the selected component. -->
      <BoxModelOverlay />

      <!-- Inline edit overlay, positioned over the iframe text being edited. -->
      <OverlayInput />
    </div>

    <!-- Phase 22 — floating action toolbar beside the selected component. It lives
         in shell space (its menus must escape the preview box) but is clamped to
         the iframe's left edge so it never slides under the sidebar. -->
    <ComponentToolbar :frame-el="iframeEl" />

    <!-- Phase 22.1 — cross-iframe drag ghost + insertion marker (shell space: the
         ghost tracks the pointer across the whole shell). -->
    <LiveDragLayer :frame-el="iframeEl" />
  </div>
</template>
