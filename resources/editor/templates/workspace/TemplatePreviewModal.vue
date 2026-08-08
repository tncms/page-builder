<script setup lang="ts">
// Phase 26.36 — template preview modal (presentation only).
//
// Shows a larger preview image + metadata for the store's `previewItem`. It does NOT render, apply,
// or execute the template — the image URL passes the existing asset boundary, and "Use template"
// simply closes the preview and hands off to the standard usage flow. No BuilderState/command/runtime.
import { computed } from 'vue'
import { useTemplateWorkspaceStore } from './templateWorkspaceStore'
import { previewImageUrl } from './templatePreview'

const store = useTemplateWorkspaceStore()

const item = computed(() => store.previewItem)
const image = computed(() => (item.value ? previewImageUrl(item.value) : undefined))
const initial = computed(() => (item.value?.name.trim()[0] ?? '?').toUpperCase())

function useTemplate(): void {
  if (!item.value) return
  const target = item.value
  store.closePreview()
  store.use(target)
}
</script>

<template>
  <div
    v-if="item"
    class="pb-tpl-preview"
    role="dialog"
    aria-modal="true"
    :aria-label="`Preview ${item.name}`"
    @click.self="store.closePreview()"
  >
    <div class="pb-tpl-preview__panel" tabindex="-1" @keydown.esc="store.closePreview()">
      <div class="pb-tpl-preview__figure">
        <img v-if="image" class="pb-tpl-preview__img" :src="image" :alt="item.name" />
        <div v-else class="pb-tpl-preview__placeholder" aria-hidden="true">{{ initial }}</div>
      </div>

      <div class="pb-tpl-preview__meta">
        <span class="pb-tpl-preview__badges">
          <span class="pb-tpl-preview__source">{{ item.source }}</span>
          <span class="pb-tpl-preview__cat">{{ item.category }}</span>
        </span>
        <h2 class="pb-tpl-preview__title">{{ item.name }}</h2>
        <p v-if="item.description" class="pb-tpl-preview__desc">{{ item.description }}</p>
      </div>

      <div class="pb-tpl-preview__actions">
        <button type="button" class="pb-tpl-preview__close" @click="store.closePreview()">Close</button>
        <button type="button" class="pb-tpl-preview__use" @click="useTemplate">Use template</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-tpl-preview {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.55);
  z-index: 50;
}
.pb-tpl-preview__panel {
  width: min(560px, 100%);
  max-height: 90vh;
  overflow: auto;
  background: #fff;
  border-radius: 0.6rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
}
.pb-tpl-preview__figure {
  aspect-ratio: 16 / 9;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 0.6rem 0.6rem 0 0;
}
.pb-tpl-preview__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pb-tpl-preview__placeholder {
  font-size: 3rem;
  font-weight: 700;
  color: #94a3b8;
}
.pb-tpl-preview__meta {
  padding: 1rem 1.25rem 0;
}
.pb-tpl-preview__badges {
  display: flex;
  gap: 0.4rem;
  font-size: 0.62rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.pb-tpl-preview__source {
  color: #2563eb;
  font-weight: 600;
}
.pb-tpl-preview__cat {
  color: #64748b;
}
.pb-tpl-preview__title {
  margin: 0.35rem 0 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.pb-tpl-preview__desc {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #475569;
}
.pb-tpl-preview__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem 1.25rem 1.25rem;
}
.pb-tpl-preview__close,
.pb-tpl-preview__use {
  padding: 0.45rem 0.9rem;
  border-radius: 0.4rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.pb-tpl-preview__close {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}
.pb-tpl-preview__use {
  border: 0;
  background: #2563eb;
  color: #fff;
}
</style>
