// Phase 16 — Frontend editor IFRAME runtime.
//
// This tiny script is injected into the owner's real public page ONLY when the
// FrontendEditorRenderer is active (pb_editor flag + page-builder.edit). It is a
// separate bundle from the admin editor — it ships no Vue. It scans the
// server-emitted component markers, reports click/hover/contextmenu up to the
// shell, and applies selection/hover/payload updates the shell sends back.
//
// It never mutates the layout or calls any API: all writes happen in the trusted
// admin shell. If the page isn't inside an iframe, it does nothing.

import { IframeBridge } from '../bridge/IframeBridge'
import type { BridgeMessage } from '../bridge/messages'
import { PB_BRIDGE_PROTOCOL_VERSION } from '../bridge/messages'
import { closestMarker, markerMeta, findMarker, MARKER_SELECTOR } from './markers'
import { installCanvasNavigationGuard } from './canvasGuard'
import { IframeDragController } from './drag/IframeDragController'
import { ColumnResizeController } from './resize/ColumnResizeController'
import { replaceComponent } from './componentRuntime'
import { Overlay } from './overlay'
import { DomPatcher } from './patcher'
import { resolveInlineTarget, readRect } from '../inline/SelectionManager'
import { locateField } from '../inline/TextLocator'
import { measureElement } from '../box-model/measure'
import './iframe.css'

interface IframeBoot {
  ownerType: string
  ownerId: string | number
  canvasId: string
}

declare global {
  interface Window {
    TN_PB_FRONTEND_EDITOR?: IframeBoot
    // Same-origin readiness marker. Set to true the instant the bridge message
    // listener is installed (before any optional subsystem is built), so the shell
    // can tell "script never loaded / crashed before boot" (marker absent) apart
    // from "loaded but messages were rejected" (marker present) when the handshake
    // times out.
    __TN_PB_IFRAME_BRIDGE_READY__?: boolean
  }
}

function idOf(value: unknown): string | null {
  if (typeof value === 'object' && value !== null && 'id' in value) {
    const id = (value as { id: unknown }).id
    return typeof id === 'string' ? id : null
  }
  return null
}

function boot(): void {
  const config = window.TN_PB_FRONTEND_EDITOR
  // Only act inside a real iframe embed; a direct visit is a harmless no-op.
  if (!config || window.parent === window.self) {
    return
  }

  const canvas = document.getElementById(config.canvasId)
  if (!canvas) {
    return
  }

  const origin = window.location.origin

  // Install the bridge + message listener FIRST — before building any optional
  // editor subsystem (overlay / patcher / drag) — so that a crash while wiring
  // those can never prevent the iframe from answering the shell's readiness
  // handshake. `handle` is a hoisted function and only runs on async message
  // events, by which point the subsystems below are already constructed.
  const bridge = new IframeBridge(window.parent, origin, (message) => handle(message))
  bridge.start()
  window.__TN_PB_IFRAME_BRIDGE_READY__ = true

  // LT-M1 Increment 2D — this document is the owner's real public page; block its
  // ordinary frontend navigation (menu / logo / language / CTA links + form
  // submits) so the editor canvas can never navigate away or switch locale. Only
  // cancels default nav — Page Builder selection/drag/inline stay fully live.
  installCanvasNavigationGuard(document)

  const overlay = new Overlay(canvas)
  const patcher = new DomPatcher(canvas)

  // The element currently being inline-edited (its text is hidden behind the
  // shell overlay while editing). Kept iframe-side for rect updates + restore.
  let editingEl: HTMLElement | null = null

  // Phase 22.1 — cross-iframe drag: owns the in-iframe pointer lifecycle once the
  // shell hands off a drag (the shell can't pointer-capture across the iframe).
  const dragController = new IframeDragController(canvas, (type, payload) =>
    bridge.send(type as Parameters<typeof bridge.send>[0], payload),
  )

  // Phase 26.2 — Visual column resize. Owns handle rendering + the pointer drag
  // locally; on commit it asks the shell to run ONE apply-column-layout command.
  const resize = new ColumnResizeController(canvas, (containerId, widths) =>
    bridge.send('pb:resize-commit', { containerId, widths }),
  )

  // Keep resize handles in sync with the shell's current selection/hover. Called
  // from the pointer handlers below and after every render reconcile.
  let hoveredId: string | null = null
  function syncResize(): void {
    resize.sync(selectedId, hoveredId)
  }

  function endInline(): void {
    if (editingEl) {
      editingEl.classList.remove('pb-fe-inline-active')
      editingEl = null
    }
  }

  // The currently selected component; its root element is measured for the box
  // model overlay whenever it changes / reflows.
  let selectedId: string | null = null

  function rootOf(componentId: string): HTMLElement | null {
    const marker = findMarker(canvas!, componentId)
    const first = marker?.firstElementChild
    return first instanceof HTMLElement ? first : null
  }

  function sendBox(): void {
    if (!selectedId) return
    const el = rootOf(selectedId)
    if (el) bridge.send('pb:box-model', measureElement(selectedId, el))
  }

  // Phase 22 — report the selected component's marker rect (iframe-relative, like
  // the box/inline rects) so the shell can float the in-canvas toolbar beside it.
  // Measures the marker wrapper (same element the selection overlay tracks).
  function sendGeometry(): void {
    if (!selectedId) return
    const marker = findMarker(canvas!, selectedId)
    if (!marker) return
    const parent = marker.parentElement?.closest(MARKER_SELECTOR)
    const parentId =
      parent instanceof HTMLElement ? parent.getAttribute('data-pb-component-id') : null
    const meta = markerMeta(marker)
    bridge.send('pb:component-geometry', {
      componentId: selectedId,
      rect: readRect(marker),
      parentId: parentId ?? null,
      kind: meta?.kind ?? '',
      type: meta?.type ?? '',
    })
  }

  // ---- Iframe -> shell: pointer interactions --------------------------
  canvas.addEventListener('click', (event) => {
    const meta = markerMeta(closestMarker(event.target, canvas))
    if (!meta) return
    overlay.setSelected(meta.id)
    // Show resize handles for the clicked container/column immediately (before the
    // shell's select round-trip lands).
    resize.sync(meta.id, hoveredId)
    bridge.send('pb:component-click', meta)

    // Phase 25.3.1 §7 — an empty column's "+ Add Element" affordance selects the
    // column (above) and asks the shell to open the Elements tab so the next
    // palette insert targets this column.
    const target = event.target instanceof Element ? event.target : null
    if (target?.closest('[data-pb-add-element]')) {
      bridge.send('pb:add-element', meta)
    }
  })

  // Cheap editable-text hover affordance (no typography/rect read on move).
  let hoverEl: HTMLElement | null = null
  function setInlineHover(el: HTMLElement | null): void {
    if (hoverEl === el) return
    hoverEl?.classList.remove('pb-fe-inline-hover')
    hoverEl = el && el !== editingEl ? el : null
    hoverEl?.classList.add('pb-fe-inline-hover')
  }

  canvas.addEventListener('mousemove', (event) => {
    // While a cross-iframe drag or a column resize is in flight, that controller
    // owns the pointer — suppress hover affordances so they don't fight.
    if (dragController.dragging || resize.dragging) return
    const marker = closestMarker(event.target, canvas)
    const meta = markerMeta(marker)
    overlay.setHovered(meta?.id ?? null)
    // Track the hovered component id "stickily" (only update over a real marker),
    // so moving the pointer off a column toward its handle doesn't drop the group.
    if (meta?.id) hoveredId = meta.id
    resize.sync(selectedId, hoveredId)
    bridge.send('pb:component-hover', meta ?? { id: null })

    // Highlight the editable field under the cursor, if any.
    const hit = marker && meta && event.target instanceof Element
      ? locateField(meta.type, marker, event.target)
      : null
    setInlineHover(hit?.el ?? null)
  })

  canvas.addEventListener('mouseleave', () => {
    overlay.setHovered(null)
    setInlineHover(null)
    bridge.send('pb:component-hover', { id: null })
  })

  canvas.addEventListener('contextmenu', (event) => {
    const meta = markerMeta(closestMarker(event.target, canvas))
    if (!meta) return
    event.preventDefault()
    overlay.setSelected(meta.id)
    bridge.send('pb:component-contextmenu', {
      ...meta,
      x: (event as MouseEvent).clientX,
      y: (event as MouseEvent).clientY,
    })
  })

  // Double-click an editable text field -> start an inline edit. The shell
  // renders the overlay input; here we only hide the real text (so only the
  // overlay shows) and report the target + its rect + typography.
  canvas.addEventListener('dblclick', (event) => {
    const found = resolveInlineTarget(event.target, canvas)
    if (!found) return

    event.preventDefault()
    endInline()

    editingEl = found.el
    editingEl.classList.add('pb-fe-inline-active')
    overlay.setSelected(found.descriptor.componentId)
    bridge.send('pb:inline-start', found.descriptor)
  })

  // Keep overlays glued to their components as the page scrolls / resizes, and
  // (while inline editing) keep the shell overlay glued to the live element.
  const reposition = () => {
    overlay.reposition()
    resize.reflow()
    if (editingEl) bridge.send('pb:inline-rect', { rect: readRect(editingEl) })
    sendBox()
    sendGeometry()
  }
  window.addEventListener('scroll', reposition, { passive: true })
  window.addEventListener('resize', reposition)

  // ---- Shell -> iframe: apply state -----------------------------------
  function handle(message: BridgeMessage): void {
    const payload = message.payload

    // Phase 22.1 — cross-iframe drag messages are owned by the drag controller.
    if (dragController.onShellMessage(message)) return

    switch (message.type) {
      // Hotfix — the shell requests readiness after the iframe's `load` event.
      // Re-announce so a boot-time pb:ready that was sent before the shell's
      // listener attached is not lost forever.
      case 'pb:handshake':
        if (import.meta.env.DEV) {
          // eslint-disable-next-line no-console
          console.debug('[pb-iframe] pb:handshake → re-announcing pb:ready')
        }
        bridge.send('pb:ready', {
          ownerType: config.ownerType,
          ownerId: config.ownerId,
          protocol: PB_BRIDGE_PROTOCOL_VERSION,
        })
        break

      case 'pb:load-payload':
      case 'pb:update-payload': {
        const html = typeof payload === 'object' && payload !== null && 'html' in payload
          ? (payload as { html: unknown }).html
          : null
        if (typeof html === 'string') {
          // Preserve scroll across the full re-render (e.g. after a drag reorder).
          const scrollX = window.scrollX
          const scrollY = window.scrollY
          canvas!.innerHTML = html
          window.scrollTo(scrollX, scrollY)
          // The authoritative HTML replaced every element: drop stale live-patch
          // tracking and any dangling inline-edit reference (elements are gone).
          patcher.reset()
          editingEl = null
          // Re-measure the (re-rendered) selected component after reconciliation.
          setTimeout(() => {
            sendBox()
            sendGeometry()
            // The DOM was fully replaced: re-resolve the resize handles' group
            // against the fresh markers so a post-commit re-render keeps them.
            resize.refresh()
          }, 0)
        }
        const selectedIdFromPayload =
          typeof payload === 'object' && payload !== null && 'selectedId' in payload
            ? (payload as { selectedId: unknown }).selectedId
            : null
        const sel = typeof selectedIdFromPayload === 'string' ? selectedIdFromPayload : null
        if (sel) selectedId = sel
        overlay.setSelected(sel)
        bridge.send('pb:geometry-update', {})
        // Hotfix — confirm the initial (or any) pushed payload was applied.
        bridge.send('pb:rendered', {})
        break
      }

      case 'pb:select-component':
        selectedId = idOf(payload)
        overlay.setSelected(selectedId)
        // Measure the newly selected component for the box model overlay + toolbar.
        sendBox()
        sendGeometry()
        syncResize()
        break

      case 'pb:hover-component':
        overlay.setHovered(idOf(payload))
        break

      case 'pb:scroll-to-component': {
        const id = idOf(payload)
        const el = id ? findMarker(canvas!, id) : null
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        overlay.reposition()
        break
      }

      // ---- Phase 17: live DOM patches --------------------------------
      case 'pb:patch-style': {
        const p = payload as { componentId?: unknown; selector?: unknown; style?: unknown }
        if (typeof p?.componentId === 'string' && p.style && typeof p.style === 'object') {
          patcher.patchStyle(
            p.componentId,
            typeof p.selector === 'string' ? p.selector : null,
            p.style as Record<string, unknown>,
          )
          overlay.reposition()
          // A live spacing patch reflows the element: re-measure so the box
          // overlay's numbers/regions track the change.
          if (p.componentId === selectedId) sendBox()
        }
        break
      }

      case 'pb:patch-text': {
        const p = payload as { componentId?: unknown; selector?: unknown; text?: unknown }
        if (typeof p?.componentId === 'string') {
          patcher.patchText(
            p.componentId,
            typeof p.selector === 'string' ? p.selector : null,
            typeof p.text === 'string' ? p.text : '',
          )
          overlay.reposition()
          // Live inline typing reflows the element: report its new rect so the
          // shell overlay resizes/repositions to follow.
          if (editingEl) bridge.send('pb:inline-rect', { rect: readRect(editingEl) })
        }
        break
      }

      // ---- Phase 21: Live Component Runtime — replace one component ---
      case 'pb:replace-component': {
        const p = payload as { componentId?: unknown; html?: unknown; selectedId?: unknown }
        if (typeof p?.componentId !== 'string' || typeof p.html !== 'string') {
          break
        }

        const ok = replaceComponent(canvas!, p.componentId, p.html)
        if (ok) {
          // The old element is gone: drop its live-patch tracking and any inline
          // reference that pointed into it.
          patcher.clear(p.componentId)
          if (editingEl && !canvas!.contains(editingEl)) editingEl = null

          // Restore selection onto the (re-rendered) component and re-measure.
          const sel = typeof p.selectedId === 'string' ? p.selectedId : selectedId
          if (sel) selectedId = sel
          overlay.setSelected(selectedId)
          overlay.reposition()
          setTimeout(() => {
            sendBox()
            sendGeometry()
            // Column markers inside the replaced component are new elements —
            // re-resolve the resize group so handles keep tracking them.
            resize.refresh()
          }, 0)
          bridge.send('pb:geometry-update', {})
        }
        // Acknowledge either way so the shell can fall back on failure.
        bridge.send('pb:component-replaced', { id: p.componentId, ok })
        break
      }

      // ---- Phase 18: end an inline edit (restore the real text) ------
      case 'pb:inline-end':
        endInline()
        break

      case 'pb:patch-attr': {
        const p = payload as { componentId?: unknown; selector?: unknown; attr?: unknown; value?: unknown }
        if (typeof p?.componentId === 'string' && typeof p.attr === 'string') {
          patcher.patchAttr(
            p.componentId,
            typeof p.selector === 'string' ? p.selector : null,
            p.attr,
            typeof p.value === 'string' ? p.value : '',
          )
        }
        break
      }

      case 'pb:clear-patches': {
        const id = idOf(payload)
        if (id) patcher.clear(id)
        break
      }

      case 'pb:refresh':
        overlay.reposition()
        resize.reflow()
        sendGeometry()
        break

      case 'pb:set-device':
        // The shell resizes the iframe element itself; re-measure + re-place the
        // resize handles for the new column widths at this breakpoint.
        overlay.reposition()
        resize.reflow()
        sendGeometry()
        break

      default:
        // Unknown types are already filtered by the bridge; nothing to do.
        break
    }
  }

  // Announce readiness so the shell can push the initial render + device. This
  // may fire before the shell's listener is attached (its `load` handler runs
  // later); the shell's pb:handshake recovers that case.
  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.debug('[pb-iframe] boot complete → pb:ready')
  }
  bridge.send('pb:ready', {
    ownerType: config.ownerType,
    ownerId: config.ownerId,
    protocol: PB_BRIDGE_PROTOCOL_VERSION,
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot)
} else {
  boot()
}
