<script setup lang="ts">
// Phase 26.47F — metadata rows for the Inspector. Rule 5: show only populated
// fields (hide empty), never show internal IDs / RID. Name is the Inspector title;
// type/source/compatibility are separate badge components.
import { computed } from 'vue'
import type { BuilderResourceItem } from '../types'

const props = defineProps<{ item: BuilderResourceItem }>()

const description = computed(() => (typeof props.item.description === 'string' ? props.item.description.trim() : ''))
const category = computed(() => props.item.category.trim())
const version = computed(() => props.item.version.trim())
const tags = computed(() => props.item.tags.filter((t) => t && t.trim() !== ''))
</script>

<template>
  <div class="pb-rm" data-testid="resource-metadata">
    <p v-if="description" class="pb-rm__desc" data-testid="meta-description">{{ description }}</p>

    <dl class="pb-rm__list">
      <template v-if="category">
        <dt>Category</dt>
        <dd data-testid="meta-category">{{ category }}</dd>
      </template>
      <template v-if="version">
        <dt>Version</dt>
        <dd data-testid="meta-version">v{{ version }}</dd>
      </template>
      <template v-if="tags.length">
        <dt>Tags</dt>
        <dd data-testid="meta-tags">
          <span v-for="tag in tags" :key="tag" class="pb-rm__tag">#{{ tag }}</span>
        </dd>
      </template>
    </dl>
  </div>
</template>

<style scoped>
.pb-rm { display: flex; flex-direction: column; gap: 0.6rem; }
.pb-rm__desc { margin: 0; font-size: 0.85rem; color: var(--pb-editor-text-muted, #9aa4b2); line-height: 1.5; }
.pb-rm__list { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 0.35rem 0.9rem; align-items: baseline; }
.pb-rm__list dt { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--pb-editor-text-muted, #9aa4b2); }
.pb-rm__list dd { margin: 0; font-size: 0.82rem; color: var(--pb-editor-text, #e7eaf0); }
.pb-rm__tag { display: inline-block; margin: 0 0.3rem 0.3rem 0; font-size: 0.68rem; color: var(--pb-editor-text-muted, #9aa4b2); }
</style>
