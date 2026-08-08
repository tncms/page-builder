<script setup lang="ts">
// Phase 26.19 — the template delete confirmation. Deleting removes the template from the
// TemplateProvider only; already-applied page content is independent and unaffected.
import { useTemplateManagementStore } from './templateManagementStore'

const store = useTemplateManagementStore()
</script>

<template>
  <div v-if="store.deletingId" class="pb-template-delete" role="dialog" aria-modal="true" aria-label="Delete template">
    <div class="pb-template-delete__backdrop" @click="store.cancelDelete()" />
    <div class="pb-template-delete__panel" @keydown.esc="store.cancelDelete()">
      <h3 class="pb-template-delete__title">Delete template</h3>
      <p class="pb-template-delete__body">
        Delete <strong>{{ store.deletingTemplate?.name }}</strong>? This removes the saved template.
        Pages already built from it are not affected.
      </p>
      <div class="pb-template-delete__actions">
        <button type="button" class="pb-template-delete__btn" @click="store.cancelDelete()">Cancel</button>
        <button type="button" class="pb-template-delete__btn pb-template-delete__btn--danger" @click="store.confirmDelete()">Delete</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-template-delete { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-template-delete__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-template-delete__panel { position: relative; width: min(22rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-template-delete__title { margin: 0; font-size: 0.95rem; font-weight: 600; }
.pb-template-delete__body { margin: 0; font-size: 0.76rem; color: #475569; line-height: 1.4; }
.pb-template-delete__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.35rem; }
.pb-template-delete__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-template-delete__btn:hover { background: #f8fafc; }
.pb-template-delete__btn--danger { background: #dc2626; border-color: #dc2626; color: #fff; }
.pb-template-delete__btn--danger:hover { background: #b91c1c; }
</style>
