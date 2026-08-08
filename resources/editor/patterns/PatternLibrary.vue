<script setup lang="ts">
// Phase 25 — the Pattern Library browse experience (search, filters, categories,
// cards, quick-preview dialog). Data-driven: everything renders from the manifests
// the server discovered — nothing is hardcoded here. Insertion goes through the
// store, which uses the existing insert-pattern command flow.
import { computed, onMounted, ref } from 'vue'
import { usePatternStore } from './patternStore'
import type { PatternFilterMode, PatternManifest } from './types'
import PatternCard from './PatternCard.vue'
import PatternPreviewDialog from './PatternPreviewDialog.vue'
import PatternVariablesDialog from './PatternVariablesDialog.vue'
import type { PatternVariableValues } from './types'

const store = usePatternStore()
const previewId = ref<string | null>(null)

onMounted(() => void store.load())

const FILTERS: Array<{ key: PatternFilterMode; label: string }> = [
  { key: 'all', label: 'All' },
  { key: 'featured', label: 'Featured' },
  { key: 'favorites', label: 'Favorites' },
  { key: 'recent', label: 'Recent' },
  { key: 'free', label: 'Free' },
]

const previewPattern = computed<PatternManifest | undefined>(() =>
  previewId.value ? store.find(previewId.value) : undefined,
)

// Insert entry point: patterns with variables open the variables dialog (via the
// store); patterns without variables insert immediately.
function requestInsert(id: string): void {
  previewId.value = null
  store.requestInsert(id)
}

async function insertWithVariables(values: PatternVariableValues): Promise<void> {
  if (store.variablesFor) await store.insert(store.variablesFor, values)
}
</script>

<template>
  <div class="pb-pattern-library">
    <div class="pb-pattern-library__search">
      <input
        type="search"
        :value="store.query"
        placeholder="Search patterns…"
        aria-label="Search patterns"
        @input="store.setQuery(($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="pb-pattern-library__filters" role="tablist" aria-label="Pattern filters">
      <button
        v-for="f in FILTERS"
        :key="f.key"
        type="button"
        class="pb-pattern-chip"
        :class="{ 'is-active': store.filterMode === f.key }"
        :aria-pressed="store.filterMode === f.key"
        @click="store.setFilterMode(f.key)"
      >
        {{ f.label }}
      </button>
    </div>

    <div v-if="store.categories.length" class="pb-pattern-library__cats">
      <button
        type="button"
        class="pb-pattern-chip"
        :class="{ 'is-active': store.activeCategory === null }"
        @click="store.setCategory(null)"
      >
        All categories
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-pattern-chip"
        :class="{ 'is-active': store.activeCategory === cat.key }"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.name }} <span class="pb-pattern-chip__count">{{ cat.count }}</span>
      </button>
    </div>

    <p v-if="store.loading" class="pb-pattern-library__state">Loading patterns…</p>
    <p v-else-if="store.error" class="pb-pattern-library__state pb-pattern-library__state--error">{{ store.error }}</p>
    <p v-else-if="store.results.length === 0" class="pb-pattern-library__state">No patterns found.</p>

    <div v-else class="pb-pattern-library__grid">
      <PatternCard
        v-for="p in store.results"
        :key="p.id"
        :pattern="p"
        :favorite="store.isFavorite(p.id)"
        :inserting="store.inserting === p.id"
        :available="store.isAvailable(p.id)"
        :unavailable-reason="store.unavailableReason(p.id)"
        @insert="requestInsert(p.id)"
        @preview="previewId = p.id"
        @toggle-favorite="store.toggleFavorite(p.id)"
      />
    </div>

    <PatternPreviewDialog
      v-if="previewPattern"
      :pattern="previewPattern"
      :favorite="store.isFavorite(previewPattern.id)"
      :inserting="store.inserting === previewPattern.id"
      @close="previewId = null"
      @insert="requestInsert(previewPattern.id)"
      @toggle-favorite="store.toggleFavorite(previewPattern.id)"
    />

    <PatternVariablesDialog
      v-if="store.variablesPattern"
      :pattern="store.variablesPattern"
      :inserting="store.inserting === store.variablesPattern.id"
      @close="store.closeVariables()"
      @insert="insertWithVariables"
    />
  </div>
</template>

<style scoped>
.pb-pattern-library { display: flex; flex-direction: column; gap: 0.55rem; padding: 0.5rem; }
.pb-pattern-library__search input { width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.78rem; }
.pb-pattern-library__filters, .pb-pattern-library__cats { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pb-pattern-chip { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 0.2rem 0.55rem; font-size: 0.68rem; cursor: pointer; color: #475569; }
.pb-pattern-chip.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-chip__count { opacity: 0.65; margin-left: 0.15rem; }
.pb-pattern-library__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.6rem; }
.pb-pattern-library__state { padding: 1rem 0.5rem; text-align: center; color: #64748b; font-size: 0.78rem; }
.pb-pattern-library__state--error { color: #dc2626; }
</style>
