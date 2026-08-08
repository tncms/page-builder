<script setup lang="ts">
// Phase 25 — a single pattern card: thumbnail, title/description, tags, badges,
// and favorite / preview / insert actions. Pure presentational — all state lives
// in the pattern store; the card only emits intent.
import { ref } from 'vue'
import type { PatternManifest } from './types'

// `available` / `unavailableReason` (Phase 25.2) let the library disable a card
// whose dependencies (plugin/component/capability) are not met, with a reason.
withDefaults(
  defineProps<{
    pattern: PatternManifest
    favorite: boolean
    inserting: boolean
    available?: boolean
    unavailableReason?: string
  }>(),
  { available: true, unavailableReason: '' },
)
defineEmits<{ insert: []; preview: []; toggleFavorite: [] }>()

// If the thumbnail endpoint fails (404/500/network), swap to a local placeholder
// exactly once — no retry, no broken-image icon, and the pattern itself stays
// fully previewable and insertable (§13).
const thumbFailed = ref(false)
function onThumbError() {
  thumbFailed.value = true
}
</script>

<template>
  <div class="pb-pattern-card">
    <button type="button" class="pb-pattern-card__thumb" :aria-label="`Preview ${pattern.name}`" @click="$emit('preview')">
      <img
        v-if="pattern.thumbnail_url && !thumbFailed"
        :src="pattern.thumbnail_url"
        :alt="pattern.name"
        loading="lazy"
        width="320"
        height="180"
        @error="onThumbError"
      />
      <span v-else class="pb-pattern-card__thumb-placeholder" aria-hidden="true">{{ (pattern.name || '?').charAt(0).toUpperCase() }}</span>
      <span v-if="pattern.featured" class="pb-pattern-badge pb-pattern-badge--featured">Featured</span>
      <span v-if="pattern.premium" class="pb-pattern-badge pb-pattern-badge--premium">Pro</span>
    </button>

    <div class="pb-pattern-card__body">
      <div class="pb-pattern-card__head">
        <h4 class="pb-pattern-card__title">{{ pattern.name }}</h4>
        <button
          type="button"
          class="pb-pattern-card__fav"
          :class="{ 'is-active': favorite }"
          :aria-label="favorite ? 'Remove from favorites' : 'Add to favorites'"
          :aria-pressed="favorite"
          @click="$emit('toggleFavorite')"
        >
          {{ favorite ? '★' : '☆' }}
        </button>
      </div>
      <p v-if="pattern.description" class="pb-pattern-card__desc">{{ pattern.description }}</p>
      <div v-if="pattern.tags.length" class="pb-pattern-card__tags">
        <span v-for="tag in pattern.tags.slice(0, 3)" :key="tag" class="pb-pattern-tag">{{ tag }}</span>
      </div>
      <p v-if="!available && unavailableReason" class="pb-pattern-card__unavailable" :title="unavailableReason">
        {{ unavailableReason }}
      </p>
      <div class="pb-pattern-card__actions">
        <button type="button" class="pb-pattern-btn" @click="$emit('preview')">Preview</button>
        <button
          type="button"
          class="pb-pattern-btn pb-pattern-btn--primary"
          :disabled="inserting || !available"
          :title="!available ? unavailableReason : undefined"
          @click="$emit('insert')"
        >
          {{ !available ? 'Unavailable' : inserting ? 'Inserting…' : 'Insert' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-pattern-card { display: flex; flex-direction: column; border: 1px solid #e2e8f0; border-radius: 0.6rem; overflow: hidden; background: #fff; }
.pb-pattern-card__thumb { position: relative; display: block; border: 0; padding: 0; cursor: pointer; background: #0f172a; aspect-ratio: 16 / 9; }
.pb-pattern-card__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pb-pattern-card__thumb-placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-size: 2rem; font-weight: 700; color: #475569; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); background-color: #334155; }
.pb-pattern-badge { position: absolute; top: 0.4rem; padding: 0.1rem 0.4rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #fff; }
.pb-pattern-badge--featured { left: 0.4rem; background: #2563eb; }
.pb-pattern-badge--premium { right: 0.4rem; background: #b45309; }
.pb-pattern-card__body { display: flex; flex-direction: column; gap: 0.35rem; padding: 0.55rem 0.65rem; }
.pb-pattern-card__head { display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; }
.pb-pattern-card__title { margin: 0; font-size: 0.8rem; font-weight: 600; }
.pb-pattern-card__fav { border: 0; background: none; cursor: pointer; color: #94a3b8; font-size: 0.95rem; line-height: 1; }
.pb-pattern-card__fav.is-active { color: #f59e0b; }
.pb-pattern-card__desc { margin: 0; font-size: 0.7rem; color: #64748b; line-height: 1.35; }
.pb-pattern-card__tags { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.pb-pattern-tag { font-size: 0.6rem; color: #475569; background: #f1f5f9; border-radius: 0.25rem; padding: 0.05rem 0.35rem; }
.pb-pattern-card__actions { display: flex; gap: 0.4rem; margin-top: 0.15rem; }
.pb-pattern-btn { flex: 1; border: 1px solid #cbd5e1; border-radius: 0.35rem; background: #fff; padding: 0.3rem; font-size: 0.72rem; cursor: pointer; }
.pb-pattern-btn:hover { background: #f8fafc; }
.pb-pattern-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-btn--primary:hover { background: #1d4ed8; }
.pb-pattern-btn:disabled { opacity: 0.6; cursor: default; }
.pb-pattern-card__unavailable { margin: 0 0 0.35rem; font-size: 0.64rem; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.3rem; padding: 0.2rem 0.4rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
