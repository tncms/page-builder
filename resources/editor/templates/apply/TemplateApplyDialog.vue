<!--
  Phase 26.23 — Template apply confirmation dialog.

  Shows the pending selection's metadata (name / source / category / description — no serialized
  blocks) and confirms intent. Apply delegates to the store, which calls the runtime; this
  component performs no layout or BuilderState work. Applying is one command / one undo.
-->
<script setup lang="ts">
import { useTemplateApplyStore } from './templateApplyStore'

const store = useTemplateApplyStore()
</script>

<template>
  <div v-if="store.isOpen" class="pb-tpl-apply" role="dialog" aria-modal="true" aria-label="Apply template">
    <div class="pb-tpl-apply__backdrop" aria-hidden="true" @click="store.cancel()" />
    <div class="pb-tpl-apply__panel" tabindex="-1" @keydown.esc="store.cancel()">
      <h3 class="pb-tpl-apply__title">Apply template</h3>

      <div v-if="store.pending" class="pb-tpl-apply__meta">
        <span class="pb-tpl-apply__name">{{ store.pending.item.name }}</span>
        <span class="pb-tpl-apply__tags">
          <span class="pb-tpl-apply__tag">{{ store.pending.source }}</span>
          <span class="pb-tpl-apply__tag">{{ store.pending.item.category }}</span>
        </span>
        <p v-if="store.pending.item.description" class="pb-tpl-apply__desc">
          {{ store.pending.item.description }}
        </p>
      </div>

      <p class="pb-tpl-apply__hint">This inserts the template into the current page (one undo step).</p>

      <p v-if="store.error" class="pb-tpl-apply__error">{{ store.error }}</p>

      <div class="pb-tpl-apply__actions">
        <button type="button" class="pb-tpl-apply__btn" :disabled="store.applying" @click="store.cancel()">
          Cancel
        </button>
        <button
          type="button"
          class="pb-tpl-apply__btn pb-tpl-apply__btn--primary"
          :disabled="store.applying"
          @click="store.confirm()"
        >
          {{ store.applying ? 'Applying…' : 'Apply' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-tpl-apply {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pb-tpl-apply__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
}
.pb-tpl-apply__panel {
  position: relative;
  width: min(24rem, 92vw);
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: 1rem;
  background: #ffffff;
  border-radius: 0.6rem;
  box-shadow: 0 12px 40px rgba(15, 23, 42, 0.28);
}
.pb-tpl-apply__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
}
.pb-tpl-apply__meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.5rem 0.6rem;
  background: #f8fafc;
  border-radius: 0.4rem;
}
.pb-tpl-apply__name {
  font-size: 0.85rem;
  font-weight: 600;
  color: #0f172a;
}
.pb-tpl-apply__tags {
  display: flex;
  gap: 0.3rem;
}
.pb-tpl-apply__tag {
  padding: 0.1rem 0.4rem;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  background: #e2e8f0;
  border-radius: 0.25rem;
}
.pb-tpl-apply__desc {
  margin: 0.1rem 0 0;
  font-size: 0.7rem;
  color: #64748b;
}
.pb-tpl-apply__hint {
  margin: 0;
  font-size: 0.7rem;
  color: #94a3b8;
}
.pb-tpl-apply__error {
  margin: 0;
  font-size: 0.72rem;
  color: #dc2626;
}
.pb-tpl-apply__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.4rem;
}
.pb-tpl-apply__btn {
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  color: #475569;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  cursor: pointer;
}
.pb-tpl-apply__btn--primary {
  color: #ffffff;
  background: #2563eb;
  border-color: #2563eb;
}
.pb-tpl-apply__btn:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>
