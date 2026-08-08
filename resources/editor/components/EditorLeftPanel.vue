<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'
import { useInsertSurfacesStore } from '../insert'
import type { BuilderPaletteGroup, BuilderResourceItem, DefinitionMeta, LayoutPreset } from '../types'
import EditorNavigator from './navigator/EditorNavigator.vue'
import BuilderResourceInspector from '../inspector/BuilderResourceInspector.vue'
import PbNotice from '../../shared-ui/PbNotice.vue'

const store = useEditorStore()
const surfaces = useInsertSurfacesStore()
const view = ref<'insert' | 'navigator'>('insert')
const tab = ref<'sections' | 'blocks' | 'layout'>('sections')

// Phase 26.38 — routing "blocks" from the unified Insert launcher brings the component palette
// forward: reveal the Insert view and switch to the Blocks tab. UI only — no insertion.
watch(
  () => surfaces.paletteToken,
  () => {
    view.value = 'insert'
    tab.value = 'blocks'
  },
)

const sections = computed<DefinitionMeta[]>(() => store.metadata?.sections ?? [])
const blocks = computed<DefinitionMeta[]>(() => store.metadata?.blocks ?? [])
const presets = computed<LayoutPreset[]>(() => store.metadata?.presets ?? [])

// Phase 26.47E — pre-built design resources for the current kind's tab, grouped by
// source (Built-in, Imported Designs, …) and category. Inserted natively via the
// store, which routes through the existing insert commands.
const resourceGroups = computed<BuilderPaletteGroup[]>(() => {
  if (tab.value === 'sections') return store.metadata?.builderResources?.section ?? []
  if (tab.value === 'blocks') return store.metadata?.builderResources?.block ?? []
  return []
})

// Phase 26.47F — clicking a resource opens the Inspector (preview-first); it never
// inserts blindly. Prev/Next navigate within the clicked resource's group; insert
// goes through the existing store pipeline (26.47E).
const inspector = ref<{ items: BuilderResourceItem[]; index: number } | null>(null)
const previewBase = computed<string>(() => store.boot?.api?.resourcePreview ?? '')

function groupItems(group: BuilderPaletteGroup): BuilderResourceItem[] {
  return group.categories.flatMap((cat) => cat.items)
}

function openInspector(group: BuilderPaletteGroup, item: BuilderResourceItem) {
  const items = groupItems(group)
  const index = Math.max(0, items.findIndex((i) => i.rid === item.rid))
  inspector.value = { items, index }
}

function setInspectorIndex(index: number) {
  if (inspector.value) inspector.value.index = index
}

async function insertFromInspector(item: BuilderResourceItem) {
  inspector.value = null
  await store.insertBuilderResource(item)
}

function labelFor(def: DefinitionMeta): string {
  return (def.label as string) || (def.name as string) || def.type
}

// Pointer-down on a palette item begins an insert drag. preventing default stops
// the browser from starting a text selection on the label, and capturing the
// pointer keeps the gesture alive even as it leaves the item. Visual mode drags
// against geometry; Outline mode drags against the tree's drop zones.
function onItemDown(def: DefinitionMeta, kind: 'section' | 'block', e: PointerEvent) {
  e.preventDefault()
  try {
    ;(e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId)
  } catch {
    // setPointerCapture can throw if the pointer is already released; ignore.
  }
  if (store.isVisualMode) store.beginPaletteDrag(def, kind, e)
  else store.beginOutlinePaletteDrag(def, kind, e)
}
</script>

<template>
  <aside class="pb-editor-panel pb-editor-panel--left">
    <div class="pb-editor-panel__views">
      <button type="button" :class="{ 'is-active': view === 'insert' }" @click="view = 'insert'">
        Insert
      </button>
      <button type="button" :class="{ 'is-active': view === 'navigator' }" @click="view = 'navigator'">
        Navigator
      </button>
    </div>

    <EditorNavigator v-if="view === 'navigator'" />

    <template v-else>
      <div class="pb-editor-panel__tabs">
        <button type="button" :class="{ 'is-active': tab === 'sections' }" @click="tab = 'sections'">
          Sections <span class="pb-editor-count">{{ sections.length }}</span>
        </button>
        <button type="button" :class="{ 'is-active': tab === 'blocks' }" @click="tab = 'blocks'">
          Blocks <span class="pb-editor-count">{{ blocks.length }}</span>
        </button>
        <button type="button" :class="{ 'is-active': tab === 'layout' }" @click="tab = 'layout'">
          Layout <span class="pb-editor-count">{{ presets.length }}</span>
        </button>
      </div>

      <PbNotice v-if="store.commandError" variant="danger" emphasis="solid" class="pb-editor-palette-error">{{ store.commandError }}</PbNotice>

      <!-- Drag an item onto the canvas, or click Add to insert via the command API. -->
      <ul v-if="tab === 'sections'" class="pb-editor-list">
        <li
          v-for="def in sections"
          :key="def.type"
          class="pb-editor-list__item pb-editor-list__item--draggable"
          @pointerdown="onItemDown(def, 'section', $event)"
        >
          <span class="pb-editor-list__label" draggable="false">{{ labelFor(def) }}</span>
          <code class="pb-editor-list__type" draggable="false">{{ def.type }}</code>
          <button
            type="button"
            class="pb-editor-list__add"
            title="Add section"
            @pointerdown.stop
            @click.stop="store.addSectionFromPalette(def)"
          >
            Add
          </button>
        </li>
      </ul>

      <ul v-else-if="tab === 'blocks'" class="pb-editor-list">
        <li
          v-for="def in blocks"
          :key="def.type"
          class="pb-editor-list__item pb-editor-list__item--draggable"
          @pointerdown="onItemDown(def, 'block', $event)"
        >
          <span class="pb-editor-list__label" draggable="false">{{ labelFor(def) }}</span>
          <code class="pb-editor-list__type" draggable="false">{{ def.type }}</code>
          <button
            type="button"
            class="pb-editor-list__add"
            title="Add block"
            @pointerdown.stop
            @click.stop="store.addBlockFromPalette(def)"
          >
            Add
          </button>
        </li>
      </ul>

      <!-- Layout presets (Phase 25.3): insert a Section → Container → Column tree. -->
      <ul v-else class="pb-editor-list pb-editor-list--presets">
        <li
          v-for="preset in presets"
          :key="preset.id"
          class="pb-editor-list__item"
        >
          <span class="pb-editor-list__label">{{ preset.label }}</span>
          <code class="pb-editor-list__type">{{ preset.columns }} col</code>
          <button
            type="button"
            class="pb-editor-list__add"
            title="Insert layout"
            @click.stop="store.addPresetFromPalette(preset)"
          >
            Add
          </button>
        </li>
      </ul>

      <!-- Design resources (Phase 26.47E): pre-built sections/blocks grouped by
           source, inserted natively via the existing insert-pattern/insert-component
           commands. Locked (FREE/PRO) items stay listed but cannot be inserted. -->
      <div v-if="resourceGroups.length" class="pb-editor-resources" data-testid="builder-resources">
        <div
          v-for="group in resourceGroups"
          :key="group.key"
          class="pb-editor-resources__group"
          :data-group="group.key"
        >
          <h3 class="pb-editor-resources__group-title">{{ group.label }}</h3>
          <div v-for="cat in group.categories" :key="cat.key" class="pb-editor-resources__category">
            <h4 class="pb-editor-resources__category-title">{{ cat.label }}</h4>
            <ul class="pb-editor-list">
              <li
                v-for="item in cat.items"
                :key="item.rid"
                class="pb-editor-list__item"
                :data-rid="item.rid"
              >
                <!-- Rule 2 — the resource is a preview card; clicking OPENS the
                     Inspector (never inserts blindly). Insertion happens there. -->
                <button
                  type="button"
                  class="pb-editor-resource"
                  data-testid="resource-open"
                  :aria-label="`Preview ${item.name}`"
                  @click="openInspector(group, item)"
                >
                  <span class="pb-editor-list__label" draggable="false">{{ item.name }}</span>
                  <span v-if="item.locked" class="pb-editor-resource__lock" aria-hidden="true">🔒</span>
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>

    <BuilderResourceInspector
      v-if="inspector"
      :items="inspector.items"
      :index="inspector.index"
      :preview-base="previewBase"
      @close="inspector = null"
      @insert="insertFromInspector"
      @update:index="setInspectorIndex"
    />
  </aside>
</template>

<style scoped>
/* Phase 26.47E/F — design-resource palette region + preview cards. */
.pb-editor-resources { display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.75rem; }
.pb-editor-resources__group-title {
  margin: 0.5rem 0 0.25rem; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--pb-editor-text-muted, #9aa4b2);
}
.pb-editor-resources__category-title {
  margin: 0.35rem 0 0.2rem; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--pb-editor-text-muted, #9aa4b2); opacity: 0.75;
}
.pb-editor-resource {
  display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%;
  text-align: left; border: 1px solid var(--pb-border, #262b36); background: var(--pb-editor-surface, #171a21);
  color: var(--pb-editor-text, #e7eaf0); border-radius: 8px; padding: 0.45rem 0.6rem; font: inherit; cursor: pointer;
}
.pb-editor-resource:hover { border-color: var(--pb-accent, #6ea8fe); }
.pb-editor-resource__lock { font-size: 0.8rem; }
</style>
