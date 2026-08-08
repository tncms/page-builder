<script setup lang="ts">
// Phase 26.12 — a single Pattern Picker card. Purely presentational: it renders a
// pattern's metadata (name, category, description, component count + type summary) and
// emits intent. No thumbnail renderer, no iframe — metadata only. State lives in the
// picker store.
import { computed } from 'vue'
import type { PatternDefinition } from '../library'
import { patternComponentCount, patternComponentSummary } from './patternFilters'

const props = withDefaults(
  defineProps<{
    pattern: PatternDefinition
    inserting?: boolean
  }>(),
  { inserting: false },
)
defineEmits<{ insert: []; select: [] }>()

const count = computed(() => patternComponentCount(props.pattern))
const summary = computed(() => patternComponentSummary(props.pattern))
const initial = computed(() => (props.pattern.name || '?').charAt(0).toUpperCase())
</script>

<template>
  <div class="pb-picker-card" :data-pattern="pattern.id">
    <button type="button" class="pb-picker-card__preview" :aria-label="`Preview ${pattern.name}`" @click="$emit('select')">
      <span class="pb-picker-card__initial" aria-hidden="true">{{ initial }}</span>
      <span v-if="pattern.category" class="pb-picker-card__cat">{{ pattern.category }}</span>
    </button>

    <div class="pb-picker-card__body">
      <h4 class="pb-picker-card__title">{{ pattern.name }}</h4>
      <p v-if="pattern.description" class="pb-picker-card__desc">{{ pattern.description }}</p>

      <p class="pb-picker-card__count">{{ count }} component{{ count === 1 ? '' : 's' }}</p>
      <div v-if="summary.length" class="pb-picker-card__types">
        <span v-for="type in summary.slice(0, 4)" :key="type" class="pb-picker-card__type">{{ type }}</span>
        <span v-if="summary.length > 4" class="pb-picker-card__type pb-picker-card__type--more">+{{ summary.length - 4 }}</span>
      </div>

      <div class="pb-picker-card__actions">
        <button type="button" class="pb-picker-btn" @click="$emit('select')">Details</button>
        <button type="button" class="pb-picker-btn pb-picker-btn--primary" :disabled="inserting" @click="$emit('insert')">
          {{ inserting ? 'Inserting…' : 'Insert' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-picker-card { display: flex; flex-direction: column; border: 1px solid #e2e8f0; border-radius: 0.6rem; overflow: hidden; background: #fff; }
.pb-picker-card__preview { position: relative; display: flex; align-items: center; justify-content: center; border: 0; padding: 0; cursor: pointer; aspect-ratio: 16 / 9; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); }
.pb-picker-card__initial { font-size: 2rem; font-weight: 700; color: #cbd5e1; }
.pb-picker-card__cat { position: absolute; bottom: 0.4rem; left: 0.4rem; padding: 0.1rem 0.4rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #fff; background: #2563eb; }
.pb-picker-card__body { display: flex; flex-direction: column; gap: 0.35rem; padding: 0.55rem 0.65rem; }
.pb-picker-card__title { margin: 0; font-size: 0.8rem; font-weight: 600; }
.pb-picker-card__desc { margin: 0; font-size: 0.7rem; color: #64748b; line-height: 1.35; }
.pb-picker-card__count { margin: 0; font-size: 0.64rem; color: #94a3b8; }
.pb-picker-card__types { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.pb-picker-card__type { font-size: 0.6rem; color: #475569; background: #f1f5f9; border-radius: 0.25rem; padding: 0.05rem 0.35rem; }
.pb-picker-card__type--more { color: #94a3b8; }
.pb-picker-card__actions { display: flex; gap: 0.4rem; margin-top: 0.15rem; }
.pb-picker-btn { flex: 1; border: 1px solid #cbd5e1; border-radius: 0.35rem; background: #fff; padding: 0.3rem; font-size: 0.72rem; cursor: pointer; }
.pb-picker-btn:hover { background: #f8fafc; }
.pb-picker-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-picker-btn--primary:hover { background: #1d4ed8; }
.pb-picker-btn:disabled { opacity: 0.6; cursor: default; }
</style>
