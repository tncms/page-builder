<script setup lang="ts">
// Phase 26.34 — Template Library Admin Workspace (the FREE v1 browse shell).
//
// Browses the Phase 26.21 Template Library with search + source/category filters and renders result
// cards. "Use template" routes through the Phase 26.32 usage workflow → the Phase 26.23 apply
// confirmation → the runtime. The workspace never applies, resolves, or touches BuilderState. No
// marketplace, selling, catalogs, or premium UI.
import { onMounted } from 'vue'
import type { TemplateLibraryItem } from '../library'
import { useTemplateWorkspaceStore } from './templateWorkspaceStore'
import WorkspaceTemplateCard from './WorkspaceTemplateCard.vue'
import WorkspaceTransferBar from './WorkspaceTransferBar.vue'
import TemplatePreviewModal from './TemplatePreviewModal.vue'

const store = useTemplateWorkspaceStore()
onMounted(() => store.load())

function onUse(item: TemplateLibraryItem): void {
  store.use(item)
}
</script>

<template>
  <section class="pb-tpl-workspace">
    <header class="pb-tpl-workspace__head">
      <h2 class="pb-tpl-workspace__title">Template Library</h2>
      <input
        class="pb-tpl-workspace__search"
        type="search"
        placeholder="Search templates…"
        :value="store.query"
        @input="store.setQuery(($event.target as HTMLInputElement).value)"
      />
    </header>

    <WorkspaceTransferBar />

    <div class="pb-tpl-workspace__filters">
      <button
        type="button"
        class="pb-tpl-workspace__chip"
        :class="{ 'is-active': store.activeSource === null }"
        @click="store.setSource(null)"
      >
        All sources
      </button>
      <button
        v-for="src in store.sources"
        :key="src.key"
        type="button"
        class="pb-tpl-workspace__chip"
        :class="{ 'is-active': store.activeSource === src.key }"
        @click="store.setSource(src.key)"
      >
        {{ src.key }} ({{ src.count }})
      </button>
    </div>

    <div class="pb-tpl-workspace__filters">
      <button
        type="button"
        class="pb-tpl-workspace__chip"
        :class="{ 'is-active': store.activeCategory === null }"
        @click="store.setCategory(null)"
      >
        All categories
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-tpl-workspace__chip"
        :class="{ 'is-active': store.activeCategory === cat.key }"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.key }} ({{ cat.count }})
      </button>
    </div>

    <p v-if="store.loading" class="pb-tpl-workspace__state">Loading templates…</p>
    <p v-else-if="store.error" class="pb-tpl-workspace__state pb-tpl-workspace__state--error">
      {{ store.error }}
    </p>
    <p v-else-if="store.isEmpty" class="pb-tpl-workspace__state">
      No templates yet. Save or import one to get started.
    </p>
    <p v-else-if="store.noResults" class="pb-tpl-workspace__state">
      No templates match your search.
    </p>

    <div v-else class="pb-tpl-workspace__grid">
      <WorkspaceTemplateCard
        v-for="item in store.results"
        :key="item.id"
        :item="item"
        @use="onUse"
        @preview="store.preview"
      />
    </div>

    <TemplatePreviewModal />
  </section>
</template>

<style scoped>
.pb-tpl-workspace {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
}
.pb-tpl-workspace__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.pb-tpl-workspace__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}
.pb-tpl-workspace__search {
  flex: 0 1 260px;
  padding: 0.4rem 0.6rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.4rem;
  font-size: 0.8rem;
}
.pb-tpl-workspace__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.pb-tpl-workspace__chip {
  padding: 0.25rem 0.6rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  font-size: 0.68rem;
  color: #475569;
  cursor: pointer;
}
.pb-tpl-workspace__chip.is-active {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
}
.pb-tpl-workspace__state {
  padding: 1.5rem;
  text-align: center;
  color: #64748b;
  font-size: 0.8rem;
}
.pb-tpl-workspace__state--error {
  color: #dc2626;
}
.pb-tpl-workspace__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
}
</style>
