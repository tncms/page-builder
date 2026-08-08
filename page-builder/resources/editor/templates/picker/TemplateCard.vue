<!--
  Phase 26.22 — Template Picker card (metadata only).

  Renders one `TemplateLibraryItem`: a thumbnail (the item's URL, used verbatim — NEVER resolved
  from a provider or media id), a source + category badge, name, and description. Clicking selects
  the template (a browse/select action); it does NOT apply, insert, or mutate anything. Apply is
  Phase 26.23.
-->
<script setup lang="ts">
import { computed } from 'vue'
import type { TemplateLibraryItem } from '../library'

const props = defineProps<{ item: TemplateLibraryItem; selected?: boolean }>()
const emit = defineEmits<{ (e: 'select'): void }>()

/** First glyph of the name, for the placeholder when there is no thumbnail URL. */
const initial = computed(() => (props.item.name.trim()[0] ?? '?').toUpperCase())
</script>

<template>
  <button
    type="button"
    class="pb-tpl-card"
    :class="{ 'pb-tpl-card--selected': props.selected }"
    :aria-pressed="props.selected ? 'true' : 'false'"
    @click="emit('select')"
  >
    <span class="pb-tpl-card__preview">
      <img v-if="props.item.thumbnail" class="pb-tpl-card__thumb" :src="props.item.thumbnail" :alt="props.item.name" />
      <span v-else class="pb-tpl-card__initial">{{ initial }}</span>
      <span class="pb-tpl-card__source">{{ props.item.source }}</span>
    </span>
    <span class="pb-tpl-card__body">
      <span class="pb-tpl-card__cat">{{ props.item.category }}</span>
      <span class="pb-tpl-card__title">{{ props.item.name }}</span>
      <span v-if="props.item.description" class="pb-tpl-card__desc">{{ props.item.description }}</span>
    </span>
  </button>
</template>

<style scoped>
.pb-tpl-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0;
  text-align: left;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 120ms ease, box-shadow 120ms ease;
}
.pb-tpl-card:hover {
  border-color: #94a3b8;
}
.pb-tpl-card--selected {
  border-color: #2563eb;
  box-shadow: 0 0 0 1px #2563eb;
}
.pb-tpl-card__preview {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 16 / 9;
  background: #f1f5f9;
}
.pb-tpl-card__thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pb-tpl-card__initial {
  font-size: 1.75rem;
  font-weight: 600;
  color: #94a3b8;
}
.pb-tpl-card__source {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  padding: 0.1rem 0.4rem;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  background: rgba(255, 255, 255, 0.85);
  border-radius: 0.25rem;
}
.pb-tpl-card__body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0 0.6rem 0.6rem;
}
.pb-tpl-card__cat {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #94a3b8;
}
.pb-tpl-card__title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #1e293b;
}
.pb-tpl-card__desc {
  font-size: 0.68rem;
  color: #64748b;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>
