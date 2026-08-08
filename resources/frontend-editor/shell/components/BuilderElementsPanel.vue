<script setup lang="ts">
// Phase 20 — "Elements" sidebar panel: the insert palette (sections + blocks).
// Reuses the structure-editor store palette actions verbatim.
import { computed, onBeforeUnmount } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import { useLiveDragStore } from '../../drag/LiveDragManager'
import type { DefinitionMeta } from '../../../editor/types'
import { resolveIconPath } from './iconLibrary'
import { usePatternStore } from '../../../editor/patterns/patternStore'
import PatternLibrary from '../../../editor/patterns/PatternLibrary.vue'
import { pbDebug } from '../../debug'
import {
  PANEL_GUIDANCE,
  capabilityOf,
  tooltipFor,
  elementTwinType,
  insertTwinLabel,
  ELEMENT_CONTEXT_HELP,
  type Capability,
} from '../builderGuidance'
import PbNotice from '../../../shared-ui/PbNotice.vue'

const store = useEditorStore()
const liveDrag = useLiveDragStore()
// Phase 25 — Patterns is the primary authoring view; Components is the raw palette.
const patterns = usePatternStore()

const sections = computed<DefinitionMeta[]>(() => store.metadata?.sections ?? [])
const blocks = computed<DefinitionMeta[]>(() => store.metadata?.blocks ?? [])

function labelOf(def: DefinitionMeta): string {
  return (def.label as string) || (def.name as string) || def.type
}

// Phase 26.3.18 — the card's accessible name = visible label + its guidance tooltip,
// so screen-reader users get the same intent hint sighted users see on hover/focus
// (no hover-only information).
function ariaFor(def: DefinitionMeta, kind: 'section' | 'block'): string {
  return `${labelOf(def)}. ${tooltipFor(def, kind)}`
}

function badgeFor(def: DefinitionMeta, kind: 'section' | 'block'): Capability {
  return capabilityOf(def.type, kind)
}

// Phase 26.3.18 — context awareness. Element cards are actionable only when the
// current selection can actually host an element (a Section, Container, or Column —
// exactly what the store's blockInsertParent() resolves). Reads reactive selection,
// so it updates as the user selects around the canvas. When false, click-insert is
// guided off and a helper explains what to do; drag-to-a-column still works.
const canHostElement = computed<boolean>(() => store.blockInsertParent() !== null)

function addSection(def: DefinitionMeta): void {
  void store.addSectionFromPalette(def)
}

function addBlock(def: DefinitionMeta): void {
  if (!canHostElement.value) return
  void store.addBlockFromPalette(def)
}

// Phase 26.3.18 — smart suggestion. Some Sections have an element twin (Image
// Section → Image, Text Section → Text). When a valid layout host is selected we
// offer to insert the element twin directly, through the normal insertComponent
// pipeline (store.addBlockFromPalette), so users who reached for a Section but meant
// an Element aren't stuck.
interface TwinSuggestion {
  twinType: string
  twinLabel: string
}

const twinSuggestions = computed<TwinSuggestion[]>(() => {
  const seen = new Set<string>()
  const out: TwinSuggestion[] = []
  for (const section of sections.value) {
    const twinType = elementTwinType(section.type)
    if (!twinType || seen.has(twinType)) continue
    const twinDef = blocks.value.find((b) => b.type === twinType)
    if (!twinDef) continue
    seen.add(twinType)
    out.push({ twinType, twinLabel: labelOf(twinDef) })
  }
  return out
})

function insertTwin(twinType: string): void {
  const twinDef = blocks.value.find((b) => b.type === twinType)
  if (!twinDef || !canHostElement.value) return
  void store.addBlockFromPalette(twinDef)
}

function iconPath(def: DefinitionMeta): string {
  return resolveIconPath(def.icon as string | undefined, def.category as string | undefined)
}

// Category display labels (Phase 23). Raw definition categories map onto the
// builder-library vocabulary; unknown categories fall back to "Utility".
const CATEGORY_LABELS: Record<string, string> = {
  layout: 'Layout',
  content: 'Content',
  media: 'Media',
  conversion: 'Conversion',
  social: 'Social',
  basic: 'Utility',
  utility: 'Utility',
}
const CATEGORY_ORDER = ['Layout', 'Content', 'Media', 'Conversion', 'Social', 'Utility']

function categoryLabel(def: DefinitionMeta): string {
  const raw = ((def.category as string) || '').toLowerCase()
  return CATEGORY_LABELS[raw] ?? 'Utility'
}

interface CategoryGroup {
  label: string
  items: DefinitionMeta[]
}

function groupByCategory(defs: DefinitionMeta[]): CategoryGroup[] {
  const buckets = new Map<string, DefinitionMeta[]>()
  for (const def of defs) {
    const label = categoryLabel(def)
    if (!buckets.has(label)) buckets.set(label, [])
    buckets.get(label)!.push(def)
  }
  return CATEGORY_ORDER.filter((label) => buckets.has(label)).map((label) => ({
    label,
    items: buckets.get(label)!,
  }))
}

const blockGroups = computed<CategoryGroup[]>(() => groupByCategory(blocks.value))

// Phase 22.1 — drag-to-insert. A small movement threshold arms the drag so a
// plain click still adds (the Add buttons keep working); crossing the threshold
// starts a cross-iframe palette drag and lets the click be.
const DRAG_THRESHOLD = 5
let armed: { def: DefinitionMeta; kind: 'section' | 'block'; x: number; y: number } | null = null

function onArmMove(event: PointerEvent): void {
  if (!armed) return
  if (Math.hypot(event.clientX - armed.x, event.clientY - armed.y) < DRAG_THRESHOLD) return
  const { def, kind } = armed
  disarm()
  liveDrag.startFromPalette(def, kind, event)
}

function onArmUp(): void {
  disarm()
}

function disarm(): void {
  armed = null
  window.removeEventListener('pointermove', onArmMove, true)
  window.removeEventListener('pointerup', onArmUp, true)
}

function armDrag(def: DefinitionMeta, kind: 'section' | 'block', event: PointerEvent): void {
  armed = { def, kind, x: event.clientX, y: event.clientY }
  window.addEventListener('pointermove', onArmMove, true)
  window.addEventListener('pointerup', onArmUp, true)
  // Phase 26.3.14 — DEV/opt-in: reveal exactly which palette card is being
  // dragged. A `kind:"section"` card (e.g. "Image Section", type:"image-section")
  // cannot nest in a Column — it snaps to the root. This trace makes a
  // wrong-card drag obvious in the browser.
  pbDebug('[PB-PALETTE-CARD]', {
    type: def.type,
    kind,
    registry: kind === 'section' ? 'sections' : 'blocks',
    label: def.label ?? def.name ?? def.type,
  })
}

onBeforeUnmount(disarm)
</script>

<template>
  <div class="pb-live-builder-panel">
    <div class="pb-live-builder-panel__modes" role="tablist" aria-label="Element source">
      <button
        type="button"
        class="pb-live-builder-panel__mode"
        :class="{ 'is-active': patterns.mode === 'patterns' }"
        :aria-selected="patterns.mode === 'patterns'"
        @click="patterns.setMode('patterns')"
      >
        Patterns
      </button>
      <button
        type="button"
        class="pb-live-builder-panel__mode"
        :class="{ 'is-active': patterns.mode === 'components' }"
        :aria-selected="patterns.mode === 'components'"
        @click="patterns.setMode('components')"
      >
        Components
      </button>
    </div>

    <template v-if="patterns.mode === 'patterns'">
      <p class="pb-live-builder-panel__desc">{{ PANEL_GUIDANCE.patterns }}</p>
      <PatternLibrary />
    </template>

    <template v-else>
      <PbNotice v-if="store.commandError" variant="danger" emphasis="solid" class="pb-live-builder-panel__error">{{ store.commandError }}</PbNotice>

      <h3 class="pb-live-builder-panel__heading">Sections</h3>
      <p class="pb-live-builder-panel__desc">{{ PANEL_GUIDANCE.sections }}</p>
    <div class="pb-live-builder-elements">
      <button
        v-for="def in sections"
        :key="'s-' + def.type"
        type="button"
        class="pb-live-builder-element"
        :title="tooltipFor(def, 'section')"
        :aria-label="ariaFor(def, 'section')"
        @click="addSection(def)"
        @pointerdown="armDrag(def, 'section', $event)"
      >
        <span class="pb-live-builder-element__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(def)" />
          </svg>
        </span>
        <span class="pb-live-builder-element__label">{{ labelOf(def) }}</span>
        <span class="pb-live-builder-element__meta">
          <span class="pb-live-builder-element__kind pb-live-builder-element__kind--section">Section</span>
          <span class="pb-live-builder-element__badge">{{ badgeFor(def, 'section') }}</span>
          <span class="pb-live-builder-element__category">{{ categoryLabel(def) }}</span>
        </span>
      </button>
    </div>

    <!-- Part 4 — smart suggestion: offer the element twin when a layout host is selected. -->
    <div v-if="canHostElement && twinSuggestions.length" class="pb-live-builder-suggest">
      <p class="pb-live-builder-suggest__title">Adding content inside a layout?</p>
      <button
        v-for="s in twinSuggestions"
        :key="'twin-' + s.twinType"
        type="button"
        class="pb-live-builder-suggest__action"
        @click="insertTwin(s.twinType)"
      >
        {{ insertTwinLabel(s.twinLabel) }}
      </button>
    </div>

    <h3 class="pb-live-builder-panel__heading">Elements</h3>
    <p class="pb-live-builder-panel__desc">{{ PANEL_GUIDANCE.elements }}</p>
    <p
      class="pb-live-builder-panel__hint"
      :class="{ 'pb-live-builder-panel__hint--warn': !canHostElement }"
      role="note"
    >
      <template v-if="canHostElement">Click to add into the selected layout, or drag onto the canvas.</template>
      <template v-else>{{ ELEMENT_CONTEXT_HELP }}</template>
    </p>
    <template v-for="group in blockGroups" :key="'g-' + group.label">
      <h4 class="pb-live-builder-panel__category">{{ group.label }}</h4>
      <div class="pb-live-builder-elements">
        <button
          v-for="def in group.items"
          :key="'b-' + def.type"
          type="button"
          class="pb-live-builder-element"
          :class="{ 'is-disabled': !canHostElement }"
          :aria-disabled="!canHostElement"
          :title="tooltipFor(def, 'block')"
          :aria-label="ariaFor(def, 'block')"
          @click="addBlock(def)"
          @pointerdown="armDrag(def, 'block', $event)"
        >
          <span class="pb-live-builder-element__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(def)" />
            </svg>
          </span>
          <span class="pb-live-builder-element__label">{{ labelOf(def) }}</span>
          <span class="pb-live-builder-element__meta">
            <span class="pb-live-builder-element__kind pb-live-builder-element__kind--element">Element</span>
            <span class="pb-live-builder-element__badge">{{ badgeFor(def, 'block') }}</span>
            <span class="pb-live-builder-element__category">{{ categoryLabel(def) }}</span>
          </span>
        </button>
      </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.pb-live-builder-panel__modes { display: flex; gap: 0.25rem; padding: 0.35rem; background: #f1f5f9; border-radius: 0.5rem; margin-bottom: 0.5rem; }
.pb-live-builder-panel__mode { flex: 1; border: 0; background: none; border-radius: 0.4rem; padding: 0.35rem; font-size: 0.75rem; font-weight: 600; color: #64748b; cursor: pointer; }
.pb-live-builder-panel__mode.is-active { background: #fff; color: #0f172a; box-shadow: 0 1px 2px rgba(15,23,42,0.12); }
</style>
