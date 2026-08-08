<script setup lang="ts">
// Phase 26.18 — the "Save as Template" dialog. A thin metadata form over the authoring
// store: scope (selection | full page), name (required), category (fixed vocabulary),
// description (optional). It never touches the component tree — Save only creates + registers
// a TemplateDefinition through the TemplateProvider.
import { useTemplateAuthoringStore } from './templateAuthoringStore'
import { TEMPLATE_LIBRARY_CATEGORIES } from './templateValidation'
import AssetPreview from '../../assets/components/AssetPreview.vue'

const store = useTemplateAuthoringStore()

function onSubmit(): void {
  store.save()
}
</script>

<template>
  <div v-if="store.open" class="pb-template-save" role="dialog" aria-modal="true" aria-label="Save as template">
    <div class="pb-template-save__backdrop" @click="store.close()" />
    <form class="pb-template-save__panel" @submit.prevent="onSubmit" @keydown.esc="store.close()">
      <h3 class="pb-template-save__title">Save as template</h3>

      <p class="pb-template-save__scope">
        Capturing:
        <strong>{{ store.scope === 'page' ? 'the full page' : 'the current selection' }}</strong>
      </p>

      <label class="pb-template-save__field">
        <span class="pb-template-save__label">Name</span>
        <input
          type="text"
          class="pb-template-save__input"
          :value="store.name"
          maxlength="80"
          placeholder="e.g. Landing Page"
          aria-required="true"
          @input="store.setName(($event.target as HTMLInputElement).value)"
        />
        <span v-if="store.errors.name" class="pb-template-save__error">{{ store.errors.name }}</span>
      </label>

      <label class="pb-template-save__field">
        <span class="pb-template-save__label">Category</span>
        <select
          class="pb-template-save__input"
          :value="store.category"
          @change="store.setCategory(($event.target as HTMLSelectElement).value)"
        >
          <option v-for="cat in TEMPLATE_LIBRARY_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
        </select>
        <span v-if="store.errors.category" class="pb-template-save__error">{{ store.errors.category }}</span>
      </label>

      <label class="pb-template-save__field">
        <span class="pb-template-save__label">Description <em>(optional)</em></span>
        <textarea
          class="pb-template-save__input"
          rows="2"
          :value="store.description"
          placeholder="What is this template for?"
          @input="store.setDescription(($event.target as HTMLTextAreaElement).value)"
        />
      </label>

      <div class="pb-template-save__field">
        <span class="pb-template-save__label">Thumbnail <em>(optional)</em></span>
        <AssetPreview :value="store.previewUrl" />
        <div class="pb-template-save__thumb-actions">
          <button type="button" class="pb-template-save__btn" @click="store.chooseThumbnail()">
            {{ store.previewUrl ? 'Replace' : 'Choose image' }}
          </button>
          <button
            v-if="store.previewUrl"
            type="button"
            class="pb-template-save__btn"
            @click="store.clearThumbnail()"
          >
            Clear
          </button>
        </div>
      </div>

      <p v-if="store.errors.root" class="pb-template-save__error pb-template-save__error--root">{{ store.errors.root }}</p>

      <div class="pb-template-save__actions">
        <button type="button" class="pb-template-save__btn" @click="store.close()">Cancel</button>
        <button type="submit" class="pb-template-save__btn pb-template-save__btn--primary">Create template</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.pb-template-save { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-template-save__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-template-save__panel { position: relative; width: min(24rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-template-save__title { margin: 0 0 0.25rem; font-size: 0.95rem; font-weight: 600; }
.pb-template-save__scope { margin: 0 0 0.15rem; font-size: 0.72rem; color: #475569; }
.pb-template-save__scope strong { color: #0f172a; }
.pb-template-save__field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-template-save__label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-template-save__label em { color: #94a3b8; font-style: normal; }
.pb-template-save__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-template-save__input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-template-save__error { font-size: 0.68rem; color: #dc2626; }
.pb-template-save__error--root { margin: 0; }
.pb-template-save__thumb-actions { display: flex; gap: 0.4rem; }
.pb-template-save__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.35rem; }
.pb-template-save__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-template-save__btn:hover { background: #f8fafc; }
.pb-template-save__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-template-save__btn--primary:hover { background: #1d4ed8; }
</style>
