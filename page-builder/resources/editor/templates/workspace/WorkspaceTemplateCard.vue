<script setup lang="ts">
// Phase 26.34 — a workspace template card (metadata-only presentation + "Use template").
// It renders library metadata and emits `use` — it never resolves, applies, or dispatches.
import { computed } from 'vue'
import type { TemplateLibraryItem } from '../library'

const props = defineProps<{ item: TemplateLibraryItem }>()
const emit = defineEmits<{
  (e: 'use', item: TemplateLibraryItem): void
  (e: 'preview', item: TemplateLibraryItem): void
}>()

/** First letter fallback when a template has no thumbnail URL. */
const initial = computed(() => (props.item.name.trim()[0] ?? '?').toUpperCase())
</script>

<template>
  <article class="pb-tpl-ws-card">
    <img
      v-if="props.item.thumbnail"
      class="pb-tpl-ws-card__thumb"
      :src="props.item.thumbnail"
      :alt="props.item.name"
    />
    <div v-else class="pb-tpl-ws-card__initial" aria-hidden="true">{{ initial }}</div>

    <div class="pb-tpl-ws-card__body">
      <div class="pb-tpl-ws-card__meta">
        <span class="pb-tpl-ws-card__source">{{ props.item.source }}</span>
        <span class="pb-tpl-ws-card__cat">{{ props.item.category }}</span>
      </div>
      <h3 class="pb-tpl-ws-card__title">{{ props.item.name }}</h3>
      <p v-if="props.item.description" class="pb-tpl-ws-card__desc">{{ props.item.description }}</p>
    </div>

    <div class="pb-tpl-ws-card__actions">
      <button type="button" class="pb-tpl-ws-card__preview" @click="emit('preview', props.item)">
        Preview
      </button>
      <button type="button" class="pb-tpl-ws-card__use" @click="emit('use', props.item)">
        Use template
      </button>
    </div>
  </article>
</template>

<style scoped>
.pb-tpl-ws-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  background: #fff;
  overflow: hidden;
}
.pb-tpl-ws-card__thumb {
  width: 100%;
  height: 120px;
  object-fit: cover;
  display: block;
  background: #f1f5f9;
}
.pb-tpl-ws-card__initial {
  width: 100%;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: #94a3b8;
  background: #f1f5f9;
}
.pb-tpl-ws-card__body {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0 0.75rem;
  text-align: left;
}
.pb-tpl-ws-card__meta {
  display: flex;
  gap: 0.4rem;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.pb-tpl-ws-card__source {
  color: #2563eb;
  font-weight: 600;
}
.pb-tpl-ws-card__cat {
  color: #64748b;
}
.pb-tpl-ws-card__title {
  margin: 0;
  font-size: 0.85rem;
  font-weight: 600;
  color: #0f172a;
}
.pb-tpl-ws-card__desc {
  margin: 0;
  font-size: 0.68rem;
  color: #475569;
}
.pb-tpl-ws-card__actions {
  display: flex;
  gap: 0.4rem;
  margin: 0.25rem 0.75rem 0.75rem;
}
.pb-tpl-ws-card__preview,
.pb-tpl-ws-card__use {
  flex: 1;
  padding: 0.4rem 0.6rem;
  border-radius: 0.35rem;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
}
.pb-tpl-ws-card__preview {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}
.pb-tpl-ws-card__preview:hover {
  border-color: #94a3b8;
}
.pb-tpl-ws-card__use {
  border: 0;
  background: #2563eb;
  color: #fff;
}
.pb-tpl-ws-card__use:hover {
  background: #1d4ed8;
}
</style>
