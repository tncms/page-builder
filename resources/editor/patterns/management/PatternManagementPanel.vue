<script setup lang="ts">
// Phase 26.14 — manage local patterns: search, filter by category, rename, duplicate,
// delete. Reads/writes only the management store (which mutates the registry and refreshes
// the picker). No editor history, no marketplace/cloud/sharing UI.
import { onMounted } from 'vue'
import { usePatternManagementStore } from './patternManagementStore'
import { patternComponentCount } from '../picker/patternFilters'
import PatternRenameDialog from './PatternRenameDialog.vue'
import PatternDeleteDialog from './PatternDeleteDialog.vue'

const store = usePatternManagementStore()

onMounted(() => store.load())
</script>

<template>
  <div class="pb-pattern-mgmt">
    <div class="pb-pattern-mgmt__search">
      <input
        type="search"
        :value="store.query"
        placeholder="Search patterns…"
        aria-label="Search patterns"
        @input="store.setQuery(($event.target as HTMLInputElement).value)"
      />
    </div>

    <div v-if="store.categories.length" class="pb-pattern-mgmt__cats" role="tablist" aria-label="Pattern categories">
      <button
        type="button"
        class="pb-pattern-mgmt__chip"
        :class="{ 'is-active': store.activeCategory === null }"
        @click="store.setCategory(null)"
      >
        All
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-pattern-mgmt__chip"
        :class="{ 'is-active': store.activeCategory === cat.key }"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.key }} <span class="pb-pattern-mgmt__chip-count">{{ cat.count }}</span>
      </button>
    </div>

    <p v-if="store.isEmpty" class="pb-pattern-mgmt__state">
      No patterns yet. Create patterns from the editor and manage them here.
    </p>
    <p v-else-if="store.noResults" class="pb-pattern-mgmt__state">No patterns match your search.</p>

    <ul v-else class="pb-pattern-mgmt__list">
      <li v-for="p in store.results" :key="p.id" class="pb-pattern-mgmt__item" :data-pattern="p.id">
        <div class="pb-pattern-mgmt__meta">
          <span class="pb-pattern-mgmt__name">{{ p.name }}</span>
          <span class="pb-pattern-mgmt__sub">
            <span v-if="p.category" class="pb-pattern-mgmt__cat">{{ p.category }}</span>
            <span class="pb-pattern-mgmt__count">{{ patternComponentCount(p) }} components</span>
          </span>
        </div>
        <div class="pb-pattern-mgmt__actions">
          <button type="button" class="pb-pattern-mgmt__btn" @click="store.openRename(p.id)">Rename</button>
          <button type="button" class="pb-pattern-mgmt__btn" @click="store.duplicate(p.id)">Duplicate</button>
          <button type="button" class="pb-pattern-mgmt__btn pb-pattern-mgmt__btn--danger" @click="store.openDelete(p.id)">Delete</button>
        </div>
      </li>
    </ul>

    <!-- Phase 26.39 — surface action feedback (rename/duplicate/delete), matching the
         Template management panel; the store already records it but it was never rendered. -->
    <p v-if="store.feedback" class="pb-pattern-mgmt__feedback" role="status">{{ store.feedback }}</p>

    <PatternRenameDialog />
    <PatternDeleteDialog />
  </div>
</template>

<style scoped>
.pb-pattern-mgmt { display: flex; flex-direction: column; gap: 0.55rem; padding: 0.5rem; }
.pb-pattern-mgmt__search input { width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.78rem; box-sizing: border-box; }
.pb-pattern-mgmt__cats { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pb-pattern-mgmt__chip { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 0.2rem 0.55rem; font-size: 0.68rem; cursor: pointer; color: #475569; text-transform: capitalize; }
.pb-pattern-mgmt__chip.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-mgmt__chip-count { opacity: 0.65; margin-left: 0.15rem; }
.pb-pattern-mgmt__state { padding: 1rem 0.5rem; text-align: center; color: #64748b; font-size: 0.76rem; }
.pb-pattern-mgmt__feedback { margin: 0; font-size: 0.68rem; color: #475569; }
.pb-pattern-mgmt__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.35rem; }
.pb-pattern-mgmt__item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.45rem; padding: 0.45rem 0.55rem; background: #fff; }
.pb-pattern-mgmt__meta { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.pb-pattern-mgmt__name { font-size: 0.78rem; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pb-pattern-mgmt__sub { display: flex; gap: 0.4rem; font-size: 0.64rem; color: #94a3b8; }
.pb-pattern-mgmt__cat { text-transform: capitalize; color: #2563eb; }
.pb-pattern-mgmt__actions { display: flex; gap: 0.3rem; flex-shrink: 0; }
.pb-pattern-mgmt__btn { border: 1px solid #cbd5e1; border-radius: 0.35rem; background: #fff; padding: 0.25rem 0.5rem; font-size: 0.68rem; cursor: pointer; color: #475569; }
.pb-pattern-mgmt__btn:hover { background: #f8fafc; }
.pb-pattern-mgmt__btn--danger { color: #dc2626; border-color: #fecaca; }
.pb-pattern-mgmt__btn--danger:hover { background: #fef2f2; }
</style>
