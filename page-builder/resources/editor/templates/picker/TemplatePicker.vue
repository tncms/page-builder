<!--
  Phase 26.22 — Template Picker (browse / search / filter / select).

  The first user-facing surface over the Phase 26.21 Template Library. Consumes ONLY the
  `TemplateLibraryProvider` via `useTemplatePickerStore`. It browses metadata and records a
  selection; it does NOT apply, insert, resolve a layout, dispatch a command, or create an undo
  entry — Apply is Phase 26.23. Source and category chips filter by invariant KEYS; only their
  visible labels would ever be localized.
-->
<script setup lang="ts">
import { onMounted } from 'vue'
import { useTemplatePickerStore } from './templatePickerStore'
import TemplateCard from './TemplateCard.vue'

const store = useTemplatePickerStore()

onMounted(() => {
  if (!store.loaded) store.load()
})
</script>

<template>
  <section class="pb-template-picker" aria-label="Template library">
    <header class="pb-template-picker__bar">
      <input
        class="pb-template-picker__search"
        type="search"
        placeholder="Search templates…"
        :value="store.query"
        @input="store.setQuery(($event.target as HTMLInputElement).value)"
      />
    </header>

    <div v-if="store.sources.length" class="pb-template-picker__filters" role="group" aria-label="Sources">
      <button
        type="button"
        class="pb-template-picker__chip"
        :aria-pressed="store.activeSource === null ? 'true' : 'false'"
        @click="store.setSource(null)"
      >
        All sources
      </button>
      <button
        v-for="src in store.sources"
        :key="src.key"
        type="button"
        class="pb-template-picker__chip"
        :aria-pressed="store.activeSource === src.key ? 'true' : 'false'"
        @click="store.setSource(src.key)"
      >
        {{ src.key }} ({{ src.count }})
      </button>
    </div>

    <div v-if="store.categories.length" class="pb-template-picker__filters" role="group" aria-label="Categories">
      <button
        type="button"
        class="pb-template-picker__chip"
        :aria-pressed="store.activeCategory === null ? 'true' : 'false'"
        @click="store.setCategory(null)"
      >
        All categories
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-template-picker__chip"
        :aria-pressed="store.activeCategory === cat.key ? 'true' : 'false'"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.key }} ({{ cat.count }})
      </button>
    </div>

    <p v-if="store.error" class="pb-template-picker__state pb-template-picker__state--error">
      {{ store.error }}
    </p>
    <p v-else-if="store.isEmpty" class="pb-template-picker__state">
      No templates yet.
    </p>
    <p v-else-if="store.noResults" class="pb-template-picker__state">
      No templates match your search.
    </p>

    <div v-else class="pb-template-picker__grid">
      <TemplateCard
        v-for="item in store.results"
        :key="item.id"
        :item="item"
        :selected="store.selectedId === item.id"
        @select="store.select(item.id)"
      />
    </div>
  </section>
</template>

<style scoped>
.pb-template-picker {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.pb-template-picker__bar {
  display: flex;
}
.pb-template-picker__search {
  width: 100%;
  padding: 0.4rem 0.6rem;
  font-size: 0.8rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
}
.pb-template-picker__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.pb-template-picker__chip {
  padding: 0.2rem 0.55rem;
  font-size: 0.66rem;
  color: #475569;
  background: #f1f5f9;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
}
.pb-template-picker__chip[aria-pressed='true'] {
  color: #ffffff;
  background: #2563eb;
}
.pb-template-picker__state {
  padding: 1.5rem 0.5rem;
  font-size: 0.75rem;
  color: #94a3b8;
  text-align: center;
}
.pb-template-picker__state--error {
  color: #dc2626;
}
.pb-template-picker__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.6rem;
}
</style>
