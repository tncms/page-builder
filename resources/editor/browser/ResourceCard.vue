<script setup lang="ts">
// PB-TEMPLATE-REGISTRY-2-B — one Resource Browser card. Presentational only: it renders
// metadata as escaped text (never v-html), shows a localized availability badge, and emits
// `select` — it never fetches a payload, previews, inserts, or applies (those are R2-C).
import { computed } from 'vue'
import type { ResourceBrowserItem, ResourceBrowserStrings } from './types'

const props = defineProps<{
  item: ResourceBrowserItem
  strings: ResourceBrowserStrings
  selected: boolean
}>()

const emit = defineEmits<{ (e: 'select', rid: string): void }>()

const thumbnail = computed<string | null>(() => {
  const t = props.item.preview?.thumbnail
  return typeof t === 'string' && t !== '' ? t : null
})

// Known sources get a localized label; an unknown source renders its raw (inert) id.
const sourceLabel = computed<string>(() => {
  const map: Record<string, string | undefined> = {
    builtin: props.strings.sourceBuiltin,
    theme: props.strings.sourceTheme,
  }
  return map[props.item.source] ?? props.item.source
})

// Availability is presentation of the canonical verdict (never recomputed here).
const badge = computed<string | null>(() => {
  if (!props.item.available) return props.strings.unavailable ?? 'Unavailable'
  if (props.item.locked) return props.strings.locked ?? 'Locked'
  return null
})
</script>

<template>
  <button
    type="button"
    class="pb-rb-card"
    :class="{ 'is-selected': selected, 'is-unavailable': !item.available }"
    :aria-pressed="selected"
    :aria-label="item.name"
    @click="emit('select', item.rid)"
  >
    <span class="pb-rb-card__thumb" aria-hidden="true">
      <img v-if="thumbnail" :src="thumbnail" :alt="item.name" loading="lazy" decoding="async" />
      <span v-else class="pb-rb-card__thumb-fallback"></span>
    </span>
    <span class="pb-rb-card__body">
      <span class="pb-rb-card__name">{{ item.name }}</span>
      <span v-if="item.description" class="pb-rb-card__desc">{{ item.description }}</span>
      <span class="pb-rb-card__meta">
        <span class="pb-rb-card__source">{{ sourceLabel }}</span>
        <span v-if="badge" class="pb-rb-card__badge" data-testid="availability-badge">
          <span class="pb-rb-card__badge-dot" aria-hidden="true"></span>{{ badge }}
        </span>
      </span>
    </span>
  </button>
</template>

<style scoped>
.pb-rb-card {
  display: flex;
  gap: 10px;
  width: 100%;
  text-align: left;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  padding: 8px;
  background: var(--pb-surface, #fff);
  color: inherit;
  cursor: pointer;
}
.pb-rb-card.is-selected {
  border-color: currentColor;
  box-shadow: inset 0 0 0 1px currentColor;
}
.pb-rb-card.is-unavailable {
  opacity: 0.7;
}
.pb-rb-card__thumb {
  flex: 0 0 56px;
  height: 56px;
  border-radius: 6px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.06);
}
.pb-rb-card__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pb-rb-card__thumb-fallback {
  display: block;
  width: 100%;
  height: 100%;
}
.pb-rb-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.pb-rb-card__name {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pb-rb-card__desc {
  font-size: 0.85em;
  opacity: 0.8;
  overflow: hidden;
}
.pb-rb-card__meta {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 0.75em;
  margin-top: 2px;
}
.pb-rb-card__badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.pb-rb-card__badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
}
</style>
