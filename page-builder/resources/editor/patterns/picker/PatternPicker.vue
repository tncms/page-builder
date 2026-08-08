<script setup lang="ts">
// Phase 26.12 — the Pattern Picker: browse registered patterns, filter by category,
// preview metadata, and insert into the current page. It reads only the picker store
// (which snapshots the Phase 26.11 registry) and inserts only through the store's
// `insert` action → `insertPattern` → the existing command pipeline. No renderer, no
// marketplace, no cloud.
import { computed, onMounted } from 'vue'
import { usePatternPickerStore } from './patternPickerStore'
import { patternComponentSummary } from './patternFilters'
import PatternCard from './PatternCard.vue'

const store = usePatternPickerStore()

// Load the registry snapshot on mount so the list reflects what is registered.
onMounted(() => store.load())

const previewTypes = computed(() =>
  store.selectedPattern ? patternComponentSummary(store.selectedPattern) : [],
)
</script>

<template>
  <div class="pb-pattern-picker">
    <div v-if="store.categories.length" class="pb-pattern-picker__cats" role="tablist" aria-label="Pattern categories">
      <button
        type="button"
        class="pb-picker-chip"
        :class="{ 'is-active': store.activeCategory === null }"
        :aria-pressed="store.activeCategory === null"
        @click="store.setCategory(null)"
      >
        All
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-picker-chip"
        :class="{ 'is-active': store.activeCategory === cat.key }"
        :aria-pressed="store.activeCategory === cat.key"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.key }} <span class="pb-picker-chip__count">{{ cat.count }}</span>
      </button>
    </div>

    <div v-if="store.isEmpty" class="pb-pattern-picker__empty">
      <p class="pb-pattern-picker__empty-title">No patterns yet.</p>
      <p class="pb-pattern-picker__empty-hint">Save a layout to create your first reusable pattern.</p>
    </div>

    <div v-else class="pb-pattern-picker__grid">
      <PatternCard
        v-for="p in store.results"
        :key="p.id"
        :pattern="p"
        :inserting="store.inserting === p.id"
        @insert="store.insert(p.id)"
        @select="store.select(p.id)"
      />
    </div>

    <div v-if="store.selectedPattern" class="pb-pattern-picker__details" role="complementary">
      <div class="pb-pattern-picker__details-head">
        <h4>{{ store.selectedPattern.name }}</h4>
        <button type="button" class="pb-picker-close" aria-label="Close details" @click="store.select(null)">×</button>
      </div>
      <p v-if="store.selectedPattern.category" class="pb-pattern-picker__details-meta">
        Category: {{ store.selectedPattern.category }}
      </p>
      <p v-if="store.selectedPattern.description" class="pb-pattern-picker__details-desc">
        {{ store.selectedPattern.description }}
      </p>
      <div v-if="previewTypes.length" class="pb-pattern-picker__details-types">
        <span class="pb-pattern-picker__details-label">Contains:</span>
        <span v-for="type in previewTypes" :key="type" class="pb-picker-card__type">{{ type }}</span>
      </div>
      <button
        type="button"
        class="pb-picker-btn pb-picker-btn--primary pb-pattern-picker__details-insert"
        :disabled="store.inserting === store.selectedPattern.id"
        @click="store.insert(store.selectedPattern.id)"
      >
        {{ store.inserting === store.selectedPattern.id ? 'Inserting…' : 'Insert pattern' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.pb-pattern-picker { display: flex; flex-direction: column; gap: 0.55rem; padding: 0.5rem; }
.pb-pattern-picker__cats { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pb-picker-chip { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 0.2rem 0.55rem; font-size: 0.68rem; cursor: pointer; color: #475569; text-transform: capitalize; }
.pb-picker-chip.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-picker-chip__count { opacity: 0.65; margin-left: 0.15rem; }
.pb-pattern-picker__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.6rem; }
.pb-pattern-picker__empty { padding: 1.5rem 0.75rem; text-align: center; }
.pb-pattern-picker__empty-title { margin: 0 0 0.35rem; font-size: 0.82rem; font-weight: 600; color: #475569; }
.pb-pattern-picker__empty-hint { margin: 0; font-size: 0.72rem; color: #94a3b8; line-height: 1.4; }
.pb-pattern-picker__details { border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #f8fafc; padding: 0.6rem 0.7rem; display: flex; flex-direction: column; gap: 0.35rem; }
.pb-pattern-picker__details-head { display: flex; align-items: center; justify-content: space-between; }
.pb-pattern-picker__details-head h4 { margin: 0; font-size: 0.82rem; font-weight: 600; }
.pb-picker-close { border: 0; background: none; font-size: 1.1rem; line-height: 1; cursor: pointer; color: #94a3b8; }
.pb-pattern-picker__details-meta { margin: 0; font-size: 0.68rem; color: #64748b; text-transform: capitalize; }
.pb-pattern-picker__details-desc { margin: 0; font-size: 0.72rem; color: #475569; line-height: 1.4; }
.pb-pattern-picker__details-types { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; }
.pb-pattern-picker__details-label { font-size: 0.66rem; color: #94a3b8; margin-right: 0.15rem; }
.pb-picker-card__type { font-size: 0.6rem; color: #475569; background: #eef2f7; border-radius: 0.25rem; padding: 0.05rem 0.35rem; }
.pb-pattern-picker__details-insert { margin-top: 0.25rem; }
.pb-picker-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; border: 1px solid #2563eb; border-radius: 0.35rem; padding: 0.35rem; font-size: 0.72rem; cursor: pointer; }
.pb-picker-btn--primary:hover { background: #1d4ed8; }
.pb-picker-btn--primary:disabled { opacity: 0.6; cursor: default; }
</style>
