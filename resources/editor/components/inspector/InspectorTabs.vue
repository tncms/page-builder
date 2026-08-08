<script setup lang="ts">
// Tab switcher + the active tab's entries (fields and groups). Tabs come straight
// from the schema. Phase 24.2: per-tab dirty dots, search-aware tab visibility
// with an empty state, and validation-driven focus (jump to the tab holding the
// first invalid field and scroll it into view).
import { computed, inject, nextTick, ref, watch } from 'vue'
import InspectorField from './InspectorField.vue'
import InspectorGroup from './InspectorGroup.vue'
import { FieldControllerKey, InspectorViewKey } from './fieldController'
import { isGroup, type SchemaEntry, type SchemaTab } from './schema'

const props = defineProps<{ tabs: SchemaTab[]; focusField?: string | null }>()

const ctrl = inject(FieldControllerKey)
const view = inject(InspectorViewKey)

const active = ref(0)
watch(
  () => props.tabs,
  () => {
    active.value = 0
  },
)

function contains(text: unknown): boolean {
  const q = (view?.query ?? '').trim().toLowerCase()
  return !q || (typeof text === 'string' && text.toLowerCase().includes(q))
}

function tabVisible(tab: SchemaTab): boolean {
  if (!view?.searching) return true
  return contains(tab.label) || contains(tab.name) || tab.fields.some((e) => view.entryVisible(e))
}

const visibleTabs = computed(() => props.tabs.filter(tabVisible))
const noResults = computed(() => view?.searching && visibleTabs.value.length === 0)

// Keep the active index on a visible tab while searching.
watch(
  () => [view?.searching, visibleTabs.value.length],
  () => {
    if (view?.searching && !tabVisible(props.tabs[active.value])) {
      const first = props.tabs.findIndex(tabVisible)
      if (first >= 0) active.value = first
    }
  },
)

const activeTab = computed(() => props.tabs[active.value] ?? null)

function dirtyInTab(tab: SchemaTab): number {
  return ctrl?.dirtyCount(tab.fields) ?? 0
}

function tabContainsField(tab: SchemaTab, name: string): boolean {
  const walk = (entries: SchemaEntry[]): boolean =>
    entries.some((e) => (isGroup(e) ? walk(e.fields) : e.name === name))
  return walk(tab.fields)
}

// Validation focus: jump to the tab holding the invalid field and scroll to it.
watch(
  () => props.focusField,
  async (name) => {
    if (!name) return
    const idx = props.tabs.findIndex((t) => tabContainsField(t, name))
    if (idx >= 0) active.value = idx
    await nextTick()
    const el = document.querySelector(`.pb-inspector-tabs [data-field="${name}"]`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    ;(el?.querySelector('input, textarea, select') as HTMLElement | null)?.focus()
  },
)
</script>

<template>
  <div class="pb-inspector-tabs">
    <div v-if="tabs.length > 1" class="pb-ui-tabs pb-inspector-tabs__bar" role="tablist">
      <button
        v-for="(tab, i) in tabs"
        v-show="tabVisible(tab)"
        :key="tab.name"
        type="button"
        role="tab"
        class="pb-ui-tab"
        :aria-selected="i === active"
        :class="{ 'is-active': i === active }"
        @click="active = i"
      >
        {{ tab.label || tab.name }}
        <span
          v-if="dirtyInTab(tab) > 0"
          class="pb-inspector-tabs__dirty-dot"
          :title="`${dirtyInTab(tab)} modified`"
          aria-hidden="true"
        ></span>
      </button>
    </div>

    <p v-if="noResults" class="pb-inspector__empty">No settings found.</p>

    <div v-else-if="activeTab" class="pb-inspector-tabs__panel" role="tabpanel">
      <template v-for="(entry, i) in activeTab.fields" :key="entry.name || i">
        <InspectorGroup v-if="isGroup(entry)" :group="entry" />
        <InspectorField v-else :field="entry" />
      </template>
    </div>
  </div>
</template>
