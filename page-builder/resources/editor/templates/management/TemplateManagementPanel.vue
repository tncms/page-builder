<script setup lang="ts">
// Phase 26.19 — the Template Management panel. Lists registered templates with search and
// category-key filtering, and Rename / Duplicate / Delete actions. Every action goes through
// the management store → TemplateProvider. No Builder, no picker, no preview.
import { onMounted } from 'vue'
import { useTemplateManagementStore } from './templateManagementStore'
import { templateSummary } from './templateActions'
import TemplateRenameDialog from './TemplateRenameDialog.vue'
import TemplateDeleteDialog from './TemplateDeleteDialog.vue'

const store = useTemplateManagementStore()

onMounted(() => store.load())
</script>

<template>
  <div class="pb-template-mgmt">
    <header class="pb-template-mgmt__head">
      <input
        type="search"
        class="pb-template-mgmt__search"
        :value="store.query"
        placeholder="Search templates…"
        aria-label="Search templates"
        @input="store.setQuery(($event.target as HTMLInputElement).value)"
      />
    </header>

    <div v-if="store.categories.length" class="pb-template-mgmt__cats">
      <button
        type="button"
        class="pb-template-mgmt__chip"
        :class="{ 'is-active': store.activeCategory === null }"
        @click="store.setCategory(null)"
      >
        All
      </button>
      <button
        v-for="cat in store.categories"
        :key="cat.key"
        type="button"
        class="pb-template-mgmt__chip"
        :class="{ 'is-active': store.activeCategory === cat.key }"
        @click="store.setCategory(cat.key)"
      >
        {{ cat.key }}<span class="pb-template-mgmt__chip-count">{{ cat.count }}</span>
      </button>
    </div>

    <p v-if="store.isEmpty" class="pb-template-mgmt__state">No templates saved yet.</p>
    <p v-else-if="store.noResults" class="pb-template-mgmt__state">No templates match your search.</p>

    <ul v-else class="pb-template-mgmt__list">
      <li v-for="tpl in store.results" :key="tpl.id" class="pb-template-mgmt__item">
        <div class="pb-template-mgmt__meta">
          <span class="pb-template-mgmt__name">{{ tpl.name }}</span>
          <span class="pb-template-mgmt__summary">
            <span v-if="tpl.category" class="pb-template-mgmt__cat">{{ tpl.category }}</span>
            {{ templateSummary(tpl) }}
          </span>
        </div>
        <div class="pb-template-mgmt__actions">
          <button type="button" class="pb-template-mgmt__btn" @click="store.openRename(tpl.id)">Rename</button>
          <button type="button" class="pb-template-mgmt__btn" @click="store.duplicate(tpl.id)">Duplicate</button>
          <button type="button" class="pb-template-mgmt__btn pb-template-mgmt__btn--danger" @click="store.openDelete(tpl.id)">Delete</button>
        </div>
      </li>
    </ul>

    <p v-if="store.feedback" class="pb-template-mgmt__feedback" role="status">{{ store.feedback }}</p>

    <TemplateRenameDialog />
    <TemplateDeleteDialog />
  </div>
</template>

<style scoped>
.pb-template-mgmt { display: flex; flex-direction: column; gap: 0.6rem; padding: 0.75rem; }
.pb-template-mgmt__head { display: flex; }
.pb-template-mgmt__search { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-template-mgmt__search:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-template-mgmt__cats { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.pb-template-mgmt__chip { display: inline-flex; align-items: center; gap: 0.3rem; border: 1px solid #cbd5e1; border-radius: 999px; background: #fff; padding: 0.25rem 0.6rem; font-size: 0.68rem; color: #475569; cursor: pointer; }
.pb-template-mgmt__chip.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-template-mgmt__chip-count { font-size: 0.64rem; opacity: 0.8; }
.pb-template-mgmt__state { margin: 0.5rem 0; font-size: 0.72rem; color: #94a3b8; }
.pb-template-mgmt__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.4rem; }
.pb-template-mgmt__item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.45rem; padding: 0.45rem 0.55rem; }
.pb-template-mgmt__meta { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.pb-template-mgmt__name { font-size: 0.8rem; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pb-template-mgmt__summary { font-size: 0.66rem; color: #94a3b8; display: flex; align-items: center; gap: 0.35rem; }
.pb-template-mgmt__cat { color: #2563eb; text-transform: capitalize; }
.pb-template-mgmt__actions { display: flex; gap: 0.3rem; flex-shrink: 0; }
.pb-template-mgmt__btn { border: 1px solid #cbd5e1; border-radius: 0.35rem; background: #fff; padding: 0.25rem 0.5rem; font-size: 0.68rem; color: #475569; cursor: pointer; }
.pb-template-mgmt__btn:hover { background: #f8fafc; }
.pb-template-mgmt__btn--danger { color: #dc2626; border-color: #fecaca; }
.pb-template-mgmt__btn--danger:hover { background: #fef2f2; }
.pb-template-mgmt__feedback { margin: 0; font-size: 0.68rem; color: #475569; }
</style>
