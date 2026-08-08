<script setup lang="ts">
// Phase 25 — large quick-preview / detail dialog for a pattern. Shows the preview
// image (pure image, no Builder render — §14/§15), description, tags, and the
// Insert / Favorite actions.
import type { PatternManifest } from './types'

defineProps<{ pattern: PatternManifest; favorite: boolean; inserting: boolean }>()
defineEmits<{ close: []; insert: []; toggleFavorite: [] }>()
</script>

<template>
  <div class="pb-pattern-dialog" role="dialog" aria-modal="true" :aria-label="pattern.name" @click.self="$emit('close')">
    <div class="pb-pattern-dialog__box" tabindex="-1" @keydown.esc="$emit('close')">
      <button type="button" class="pb-pattern-dialog__close" aria-label="Close preview" @click="$emit('close')">×</button>
      <div class="pb-pattern-dialog__media">
        <img :src="pattern.thumbnail_url" :alt="pattern.name" />
      </div>
      <div class="pb-pattern-dialog__info">
        <h3 class="pb-pattern-dialog__title">{{ pattern.name }}</h3>
        <p v-if="pattern.description" class="pb-pattern-dialog__desc">{{ pattern.description }}</p>
        <div v-if="pattern.tags.length" class="pb-pattern-dialog__tags">
          <span v-for="tag in pattern.tags" :key="tag" class="pb-pattern-tag">{{ tag }}</span>
        </div>
        <p v-if="pattern.requiredComponents?.length" class="pb-pattern-dialog__components">
          Components: {{ pattern.requiredComponents.join(', ') }}
        </p>
        <div class="pb-pattern-dialog__actions">
          <button type="button" class="pb-pattern-btn" @click="$emit('toggleFavorite')">
            {{ favorite ? '★ Favorited' : '☆ Favorite' }}
          </button>
          <button
            type="button"
            class="pb-pattern-btn pb-pattern-btn--primary"
            :disabled="inserting"
            @click="$emit('insert')"
          >
            {{ inserting ? 'Inserting…' : 'Insert pattern' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-pattern-dialog { position: fixed; inset: 0; z-index: 2147483200; display: flex; align-items: center; justify-content: center; padding: 2rem; background: rgba(15, 23, 42, 0.55); }
.pb-pattern-dialog__box { position: relative; width: 100%; max-width: 640px; max-height: 90vh; overflow: auto; background: #fff; border-radius: 0.75rem; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.4); }
.pb-pattern-dialog__close { position: absolute; top: 0.5rem; right: 0.5rem; border: 0; background: rgba(255,255,255,0.85); border-radius: 50%; width: 1.75rem; height: 1.75rem; font-size: 1.1rem; cursor: pointer; }
.pb-pattern-dialog__media { background: #0f172a; }
.pb-pattern-dialog__media img { width: 100%; display: block; }
.pb-pattern-dialog__info { padding: 1rem 1.15rem 1.15rem; display: flex; flex-direction: column; gap: 0.5rem; }
.pb-pattern-dialog__title { margin: 0; font-size: 1.05rem; font-weight: 700; }
.pb-pattern-dialog__desc { margin: 0; color: #475569; font-size: 0.85rem; }
.pb-pattern-dialog__tags { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pb-pattern-tag { font-size: 0.65rem; color: #475569; background: #f1f5f9; border-radius: 0.25rem; padding: 0.1rem 0.4rem; }
.pb-pattern-dialog__components { margin: 0; font-size: 0.7rem; color: #94a3b8; }
.pb-pattern-dialog__actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
.pb-pattern-btn { flex: 1; border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.5rem; font-size: 0.8rem; cursor: pointer; }
.pb-pattern-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-btn:disabled { opacity: 0.6; cursor: default; }
</style>
