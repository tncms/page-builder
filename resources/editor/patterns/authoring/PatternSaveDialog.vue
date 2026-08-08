<script setup lang="ts">
// Phase 26.13 — the "Save as Pattern" dialog. A thin metadata form over the authoring
// store: name (required), category (fixed vocabulary), description (optional). It never
// touches the component tree — Save only creates + registers a PatternDefinition.
import { usePatternAuthoringStore } from './patternAuthoringStore'
import { PATTERN_LIBRARY_CATEGORIES } from '../library'

const store = usePatternAuthoringStore()

function onSubmit(): void {
  store.save()
}
</script>

<template>
  <div v-if="store.open" class="pb-pattern-save" role="dialog" aria-modal="true" aria-label="Save as pattern">
    <div class="pb-pattern-save__backdrop" @click="store.close()" />
    <form class="pb-pattern-save__panel" @submit.prevent="onSubmit" @keydown.esc="store.close()">
      <h3 class="pb-pattern-save__title">Save as pattern</h3>

      <label class="pb-pattern-save__field">
        <span class="pb-pattern-save__label">Name</span>
        <input
          type="text"
          class="pb-pattern-save__input"
          :value="store.name"
          maxlength="80"
          placeholder="e.g. Hero Landing"
          aria-required="true"
          @input="store.setName(($event.target as HTMLInputElement).value)"
        />
        <span v-if="store.errors.name" class="pb-pattern-save__error">{{ store.errors.name }}</span>
      </label>

      <label class="pb-pattern-save__field">
        <span class="pb-pattern-save__label">Category</span>
        <select
          class="pb-pattern-save__input"
          :value="store.category"
          @change="store.setCategory(($event.target as HTMLSelectElement).value)"
        >
          <option v-for="cat in PATTERN_LIBRARY_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
        </select>
        <span v-if="store.errors.category" class="pb-pattern-save__error">{{ store.errors.category }}</span>
      </label>

      <label class="pb-pattern-save__field">
        <span class="pb-pattern-save__label">Description <em>(optional)</em></span>
        <textarea
          class="pb-pattern-save__input"
          rows="2"
          :value="store.description"
          placeholder="What is this pattern for?"
          @input="store.setDescription(($event.target as HTMLTextAreaElement).value)"
        />
      </label>

      <p v-if="store.errors.root" class="pb-pattern-save__error pb-pattern-save__error--root">{{ store.errors.root }}</p>

      <div class="pb-pattern-save__actions">
        <button type="button" class="pb-pattern-save__btn" @click="store.close()">Cancel</button>
        <button type="submit" class="pb-pattern-save__btn pb-pattern-save__btn--primary">Create pattern</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.pb-pattern-save { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-pattern-save__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-pattern-save__panel { position: relative; width: min(24rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-pattern-save__title { margin: 0 0 0.25rem; font-size: 0.95rem; font-weight: 600; }
.pb-pattern-save__field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-pattern-save__label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-pattern-save__label em { color: #94a3b8; font-style: normal; }
.pb-pattern-save__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-pattern-save__input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-pattern-save__error { font-size: 0.68rem; color: #dc2626; }
.pb-pattern-save__error--root { margin: 0; }
.pb-pattern-save__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.35rem; }
.pb-pattern-save__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-pattern-save__btn:hover { background: #f8fafc; }
.pb-pattern-save__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-save__btn--primary:hover { background: #1d4ed8; }
</style>
