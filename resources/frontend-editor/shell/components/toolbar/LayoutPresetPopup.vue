<script setup lang="ts">
// Phase 26.1.1 — Layout preset popup (Elementor/Bricks-style), 100% metadata
// driven. It renders whatever presets/categories the backend metadata provides
// (external plugins may add more) — no hardcoded ratios or switch statements.
//
// Behaviour:
//  - Recent + Favorites sections (from the localStorage-backed preferences store)
//    sit above the metadata category tabs (All + layoutCategories).
//  - Instant client-side search over label/description/category/keywords.
//  - Roving-tabindex keyboard nav (Arrows/Home/End/Enter/Escape) — a real listbox.
//  - The card matching the container's current column widths is badged "✓ Current"
//    (aria-current); when none match, a "Custom Layout" hint shows. Nothing else
//    changes on open.
//  - Hovering a card previews the re-proportioning in the iframe (send on enter,
//    restore on leave/close/apply) via the layout hover-preview store — a preview
//    only, never a commit. Applying still routes through toolbar.applyLayoutPreset
//    (the single unchanged command path), then records the preset as recent.
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useToolbarStore } from '../../../toolbar/ToolbarManager'
import { useEditorStore } from '../../../../editor/stores/editor'
import { useLayoutPreferences } from '../../../toolbar/useLayoutPreferences'
import { useLayoutHoverPreview } from '../../../toolbar/layoutHoverPreview'
import { presetLosesContent } from '../../../toolbar/layoutContentLoss'
import { currentLayoutPresetId } from '../../../toolbar/currentLayout'
import { presetMatchesQuery, orderedPresets, gridColumnCount } from '../../../toolbar/presetCatalog'
import PresetPreview from './PresetPreview.vue'
import type { ColumnLayoutPreset } from '../../../../editor/types'

const toolbar = useToolbarStore()
const editor = useEditorStore()
const preferences = useLayoutPreferences()
const hover = useLayoutHoverPreview()

type TabId = 'all' | 'favorites' | 'recent' | string

const activeTab = ref<TabId>('all')
const query = ref('')
const focusedIndex = ref(0)
const gridEl = ref<HTMLElement | null>(null)

onMounted(() => preferences.load())

// All presets from metadata, ordered by their declared `order` (stable fallback).
const allPresets = computed<ColumnLayoutPreset[]>(() =>
  orderedPresets(editor.metadata?.columnLayouts ?? []),
)

// Category tabs come from metadata only; the popup adds All/Favorites/Recent.
const categories = computed(() => editor.metadata?.layoutCategories ?? [])

const hasRecent = computed(() => recentPresets.value.length > 0)
const hasFavorites = computed(() => favoritePresets.value.length > 0)

const byId = computed<Map<string, ColumnLayoutPreset>>(
  () => new Map(allPresets.value.map((p) => [p.id, p])),
)

// Favorites first (in metadata order), for the Favorites view.
const favoritePresets = computed<ColumnLayoutPreset[]>(() =>
  allPresets.value.filter((p) => preferences.isFavorite(p.id)),
)

// Recents in most-recent-first order, mapped back to live presets (skip unknown).
const recentPresets = computed<ColumnLayoutPreset[]>(() =>
  preferences.recent.map((id) => byId.value.get(id)).filter((p): p is ColumnLayoutPreset => !!p),
)

// The preset the current container matches (only a container has a live layout;
// a Section inserts a fresh container, so it never matches — "Custom").
const currentId = computed<string | null>(() => {
  if (toolbar.actionKind !== 'container') return null
  return currentLayoutPresetId(editor.currentPayload, editor.selectedComponentId, allPresets.value)
})

// The visible cards: the active tab's set, then filtered by the search query.
const visiblePresets = computed<ColumnLayoutPreset[]>(() => {
  let base: ColumnLayoutPreset[]
  if (activeTab.value === 'favorites') base = favoritePresets.value
  else if (activeTab.value === 'recent') base = recentPresets.value
  else if (activeTab.value === 'all') base = allPresets.value
  else base = allPresets.value.filter((p) => (p.category ?? '') === activeTab.value)

  const q = query.value.trim()
  if (!q) return base
  return base.filter((p) => presetMatchesQuery(p, q))
})

const columnCount = computed(() => gridColumnCount(visiblePresets.value.length))

function isCurrent(preset: ColumnLayoutPreset): boolean {
  return preset.id === currentId.value
}

function selectTab(id: TabId): void {
  activeTab.value = id
  focusedIndex.value = 0
  cancelPreview()
}

// --- Apply (unchanged command path) ---------------------------------------
function choose(preset: ColumnLayoutPreset): void {
  cancelPreview()
  const id = editor.selectedComponentId
  if (
    id &&
    toolbar.actionKind === 'container' &&
    presetLosesContent(editor.currentPayload, id, preset.widths.length)
  ) {
    const ok = window.confirm(
      'This layout has fewer columns. Columns with content will be removed. Continue?',
    )
    if (!ok) return
  }
  void toolbar.applyLayoutPreset(preset.widths)
  preferences.addRecent(preset.id)
}

function toggleFavorite(preset: ColumnLayoutPreset, event: Event): void {
  event.stopPropagation()
  preferences.toggleFavorite(preset.id)
}

// --- Hover preview ---------------------------------------------------------
function onHover(preset: ColumnLayoutPreset): void {
  // Only a live container can be previewed; a Section has no existing columns.
  const id = editor.selectedComponentId
  if (!id || toolbar.actionKind !== 'container') return
  hover.preview(id, preset.widths, preset.id)
}

function cancelPreview(): void {
  if (hover.previewing) hover.restore()
}

// --- Keyboard (roving tabindex, grid-aware) --------------------------------
function focusCard(index: number): void {
  const cards = gridEl.value?.querySelectorAll<HTMLElement>('[data-preset-card]')
  cards?.[index]?.focus()
}

function moveFocus(delta: number): void {
  const count = visiblePresets.value.length
  if (count === 0) return
  focusedIndex.value = Math.max(0, Math.min(count - 1, focusedIndex.value + delta))
  void nextTick(() => focusCard(focusedIndex.value))
}

function onGridKeydown(event: KeyboardEvent): void {
  const cols = columnCount.value
  switch (event.key) {
    case 'ArrowRight':
      event.preventDefault()
      moveFocus(1)
      break
    case 'ArrowLeft':
      event.preventDefault()
      moveFocus(-1)
      break
    case 'ArrowDown':
      event.preventDefault()
      moveFocus(cols)
      break
    case 'ArrowUp':
      event.preventDefault()
      moveFocus(-cols)
      break
    case 'Home':
      event.preventDefault()
      focusedIndex.value = 0
      void nextTick(() => focusCard(0))
      break
    case 'End':
      event.preventDefault()
      focusedIndex.value = visiblePresets.value.length - 1
      void nextTick(() => focusCard(focusedIndex.value))
      break
    case 'Enter':
    case ' ': {
      event.preventDefault()
      const preset = visiblePresets.value[focusedIndex.value]
      if (preset) choose(preset)
      break
    }
    case 'Escape':
      event.preventDefault()
      close()
      break
  }
}

function close(): void {
  cancelPreview()
  toolbar.closeLayoutPopup()
}

// Restore the preview and reset roving focus whenever the visible set changes or
// the popup closes, so a stale patch never lingers in the iframe.
watch(
  () => toolbar.layoutPopupOpen,
  (open) => {
    if (!open) cancelPreview()
    else {
      activeTab.value = 'all'
      query.value = ''
      focusedIndex.value = 0
    }
  },
)
watch(query, () => {
  focusedIndex.value = 0
  // A hovered card filtered out of the grid never fires @mouseleave, so clear any
  // active hover preview here to avoid a lingering patch in the iframe.
  cancelPreview()
})
</script>

<template>
  <div
    v-if="toolbar.layoutPopupOpen"
    class="pb-live-toolbar__layout-popup"
    @mouseleave="cancelPreview"
    @keydown.esc.prevent="close"
  >
    <!-- Search -->
    <input
      v-model="query"
      type="search"
      class="pb-live-toolbar__layout-search"
      aria-label="Search layouts"
      placeholder="Search layouts…"
    />

    <!-- Tabs: All + metadata categories (Favorites/Recent are their own views) -->
    <div class="pb-live-toolbar__layout-tabs" role="tablist" aria-label="Layout categories">
      <button
        type="button"
        role="tab"
        aria-controls="pb-layout-grid"
        class="pb-live-toolbar__layout-tab"
        :aria-selected="activeTab === 'all'"
        :class="{ 'is-active': activeTab === 'all' }"
        @click="selectTab('all')"
      >
        All
      </button>
      <button
        v-if="hasFavorites"
        type="button"
        role="tab"
        aria-controls="pb-layout-grid"
        class="pb-live-toolbar__layout-tab"
        :aria-selected="activeTab === 'favorites'"
        :class="{ 'is-active': activeTab === 'favorites' }"
        @click="selectTab('favorites')"
      >
        Favorites
      </button>
      <button
        v-if="hasRecent"
        type="button"
        role="tab"
        aria-controls="pb-layout-grid"
        class="pb-live-toolbar__layout-tab"
        :aria-selected="activeTab === 'recent'"
        :class="{ 'is-active': activeTab === 'recent' }"
        @click="selectTab('recent')"
      >
        Recent
      </button>
      <button
        v-for="cat in categories"
        :key="cat.id"
        type="button"
        role="tab"
        aria-controls="pb-layout-grid"
        class="pb-live-toolbar__layout-tab"
        :aria-selected="activeTab === cat.id"
        :class="{ 'is-active': activeTab === cat.id }"
        @click="selectTab(cat.id)"
      >
        {{ cat.label }}
      </button>
    </div>

    <!-- Cards grid -->
    <div
      id="pb-layout-grid"
      ref="gridEl"
      class="pb-live-toolbar__layout-grid"
      role="listbox"
      aria-label="Choose a column layout"
      @keydown="onGridKeydown"
    >
      <div
        v-for="(preset, i) in visiblePresets"
        :key="preset.id"
        data-preset-card
        role="option"
        class="pb-live-toolbar__layout-card"
        :class="{ 'is-current': isCurrent(preset) }"
        :data-preset-id="preset.id"
        :aria-selected="isCurrent(preset)"
        :aria-current="isCurrent(preset) ? 'true' : undefined"
        :tabindex="i === focusedIndex ? 0 : -1"
        :title="preset.label"
        @click="choose(preset)"
        @mouseenter="focusedIndex = i; onHover(preset)"
        @focus="focusedIndex = i"
      >
        <button
          type="button"
          class="pb-live-toolbar__layout-star"
          :class="{ 'is-on': preferences.isFavorite(preset.id) }"
          :aria-label="preferences.isFavorite(preset.id) ? 'Remove from favorites' : 'Add to favorites'"
          :aria-pressed="preferences.isFavorite(preset.id)"
          @click="toggleFavorite(preset, $event)"
        >
          ★
        </button>
        <PresetPreview :widths="preset.widths" :preview="preset.preview" />
        <span class="pb-live-toolbar__layout-label">{{ preset.label }}</span>
        <span v-if="preset.description" class="pb-live-toolbar__layout-desc">{{ preset.description }}</span>
        <span v-if="isCurrent(preset)" class="pb-live-toolbar__layout-current">✓ Current</span>
      </div>

      <p v-if="visiblePresets.length === 0" class="pb-live-toolbar__layout-empty">
        No layouts match “{{ query }}”.
      </p>
    </div>

    <!-- Custom-layout hint when the container matches no preset -->
    <p
      v-if="toolbar.actionKind === 'container' && currentId === null"
      class="pb-live-toolbar__layout-hint"
    >
      Custom Layout
    </p>
  </div>
</template>
