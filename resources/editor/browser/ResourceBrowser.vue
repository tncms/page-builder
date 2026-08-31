<script setup lang="ts">
// PB-TEMPLATE-REGISTRY-2-B — the ONE shared Resource Browser shell. Both the Structure
// Builder and the Frontend/Live Builder mount this same component, bound to the ONE shared
// `resourceBrowserStore`. It is presentation only: it browses metadata (Patterns =
// sections, Templates = pages), searches/filters/sorts/counts, and reserves a selection
// seam for R2-C. It never previews, inserts, applies, mutates the draft, or touches CAS.
import { computed, nextTick, ref, watch } from 'vue'
import { useResourceBrowserStore } from './resourceBrowserStore'
import type { AvailabilityFilter, ResourceKind } from './types'
import ResourceCard from './ResourceCard.vue'
import ResourceDetailPane from './ResourceDetailPane.vue'
import TemplateConfirmDialog from './TemplateConfirmDialog.vue'

const store = useResourceBrowserStore()

const searchInput = ref<HTMLInputElement | null>(null)

// Focus the search field when the browser opens (focus entry); Escape closes.
watch(
  () => store.open,
  (open) => {
    if (open) void nextTick(() => searchInput.value?.focus())
  },
)

const s = computed(() => (key: string, fallback = ''): string => store.label(key, fallback))

const kinds: Array<{ value: ResourceKind; key: string; fallback: string }> = [
  { value: 'pattern', key: 'patterns', fallback: 'Patterns' },
  { value: 'template', key: 'templates', fallback: 'Templates' },
]

const availabilityOptions: Array<{ value: AvailabilityFilter; key: string; fallback: string }> = [
  { value: 'all', key: 'all', fallback: 'All' },
  { value: 'available', key: 'available', fallback: 'Available' },
  { value: 'unavailable', key: 'unavailable', fallback: 'Unavailable' },
]

const sourceLabel = (value: string): string => {
  if (value === 'builtin') return s.value('sourceBuiltin', 'Built-in')
  if (value === 'theme') return s.value('sourceTheme', 'Theme')
  return value
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.stopPropagation()
    store.closeBrowser()
  }
}
</script>

<template>
  <div
    v-if="store.open"
    class="pb-rb"
    role="dialog"
    aria-modal="true"
    :aria-label="s('title', 'Resource Browser')"
    @keydown="onKeydown"
  >
    <div class="pb-rb__panel">
      <header class="pb-rb__header">
        <h2 class="pb-rb__title">{{ s('title', 'Resource Browser') }}</h2>
        <button type="button" class="pb-rb__close" :aria-label="s('close', 'Close')" @click="store.closeBrowser()">
          &times;
        </button>
      </header>

      <div class="pb-rb__tabs" role="tablist" :aria-label="s('title', 'Resource Browser')">
        <button
          v-for="k in kinds"
          :key="k.value"
          type="button"
          role="tab"
          class="pb-rb__tab"
          :class="{ 'is-active': store.activeKind === k.value }"
          :aria-selected="store.activeKind === k.value"
          @click="store.setKind(k.value)"
        >
          {{ s(k.key, k.fallback) }}
        </button>
      </div>

      <div class="pb-rb__controls">
        <label class="pb-rb__search">
          <span class="pb-rb__sr">{{ s('search', 'Search resources') }}</span>
          <input
            ref="searchInput"
            type="search"
            class="pb-rb__search-input"
            :placeholder="s('search', 'Search resources')"
            :value="store.query"
            @input="store.setQuery(($event.target as HTMLInputElement).value)"
          />
        </label>

        <label class="pb-rb__filter">
          <span class="pb-rb__sr">{{ s('source', 'Source') }}</span>
          <select class="pb-rb__select" :value="store.source" @change="store.setSource(($event.target as HTMLSelectElement).value)">
            <option value="all">{{ s('all', 'All') }}</option>
            <option v-for="o in store.sourceOptions" :key="o.value" :value="o.value">{{ sourceLabel(o.value) }}</option>
          </select>
        </label>

        <label class="pb-rb__filter">
          <span class="pb-rb__sr">{{ s('category', 'Category') }}</span>
          <select class="pb-rb__select" :value="store.category" @change="store.setCategory(($event.target as HTMLSelectElement).value)">
            <option value="all">{{ s('all', 'All') }}</option>
            <option v-for="o in store.categoryOptions" :key="o.value" :value="o.value">{{ store.categoryLabels[o.value] ?? o.value }}</option>
          </select>
        </label>

        <label class="pb-rb__filter">
          <span class="pb-rb__sr">{{ s('availability', 'Availability') }}</span>
          <select class="pb-rb__select" :value="store.availability" @change="store.setAvailability(($event.target as HTMLSelectElement).value as AvailabilityFilter)">
            <option v-for="o in availabilityOptions" :key="o.value" :value="o.value">{{ s(o.key, o.fallback) }}</option>
          </select>
        </label>
      </div>

      <div class="pb-rb__toolbar">
        <span class="pb-rb__count" role="status" aria-live="polite">{{ store.count }} {{ s('results', 'results') }}</span>
        <button
          v-if="store.hasActiveFilters"
          type="button"
          class="pb-rb__clear"
          @click="store.resetFilters()"
        >
          {{ s('clearFilters', 'Clear filters') }}
        </button>
      </div>

      <div class="pb-rb__body">
        <p v-if="store.loading" class="pb-rb__state" role="status" aria-live="polite">{{ s('loading', 'Loading resources…') }}</p>

        <div v-else-if="store.error" class="pb-rb__state pb-rb__state--error" role="alert">
          <span>{{ store.error }}</span>
        </div>

        <p v-else-if="store.isEmptyKind" class="pb-rb__state" data-testid="empty-state">{{ s('empty', 'No resources yet') }}</p>

        <p v-else-if="store.isNoResults" class="pb-rb__state" data-testid="no-results" role="status" aria-live="polite">
          {{ s('noResults', 'No matching resources') }}
        </p>

        <ul v-else class="pb-rb__grid" role="list">
          <li v-for="item in store.results" :key="item.rid" class="pb-rb__grid-item">
            <ResourceCard
              :item="item"
              :strings="store.strings"
              :selected="store.selectedRid === item.rid"
              @select="store.select"
            />
          </li>
        </ul>
      </div>

      <!-- PB-TEMPLATE-REGISTRY-2-C — non-mutating preview/detail over the list (master-detail). -->
      <ResourceDetailPane v-if="store.detailOpen" />
    </div>

    <!-- PB-TEMPLATE-REGISTRY-2-C — stage-2 Template replacement confirmation (draft-only). -->
    <TemplateConfirmDialog />
  </div>
</template>

<style scoped>
.pb-rb {
  position: fixed;
  inset: 0;
  /* PB-TEMPLATE-REGISTRY-2-H1 — the modal owns the overlay layer above ALL editor chrome
     (the Structure Editor picker toolbar and the Live Builder in-canvas component toolbar). */
  z-index: var(--pb-ui-z-modal);
  display: flex;
  justify-content: flex-end;
  background: rgba(0, 0, 0, 0.4);
}
.pb-rb__panel {
  position: relative; /* anchors the R2-C detail pane overlay */
  display: flex;
  flex-direction: column;
  width: min(880px, 100%);
  max-height: 100vh;
  background: var(--pb-surface, #fff);
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.2);
}
.pb-rb__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}
.pb-rb__title {
  font-size: 1rem;
  margin: 0;
}
.pb-rb__close {
  border: 0;
  background: none;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  color: inherit;
}
.pb-rb__tabs {
  display: flex;
  gap: 4px;
  padding: 8px 16px 0;
}
.pb-rb__tab {
  border: 0;
  background: none;
  padding: 8px 12px;
  cursor: pointer;
  color: inherit;
  border-bottom: 2px solid transparent;
}
.pb-rb__tab.is-active {
  border-bottom-color: currentColor;
  font-weight: 600;
}
.pb-rb__controls {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px 16px;
}
.pb-rb__search {
  flex: 1 1 200px;
}
.pb-rb__filter {
  flex: 0 1 140px;
}
.pb-rb__search-input,
.pb-rb__select {
  width: 100%;
  padding: 6px 8px;
  box-sizing: border-box;
}
.pb-rb__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
.pb-rb__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 16px 8px;
}
.pb-rb__clear {
  border: 0;
  background: none;
  cursor: pointer;
  color: inherit;
  text-decoration: underline;
}
.pb-rb__body {
  flex: 1 1 auto;
  overflow: auto;
  padding: 8px 16px 16px;
}
.pb-rb__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  list-style: none;
  margin: 0;
  padding: 0;
}
.pb-rb__state {
  padding: 24px 0;
  text-align: center;
  opacity: 0.85;
}
.pb-rb__state--error {
  color: #b00020;
}
.pb-rb :focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
@media (max-width: 640px) {
  .pb-rb__panel {
    width: 100%;
  }
  .pb-rb__filter {
    flex-basis: 100%;
  }
}
@media (prefers-reduced-motion: reduce) {
  .pb-rb * {
    transition: none !important;
    animation: none !important;
  }
}
</style>
