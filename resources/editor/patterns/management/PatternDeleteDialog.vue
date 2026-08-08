<script setup lang="ts">
// Phase 26.14 — confirm deleting a local pattern. Removes the registry entry only;
// already-inserted copies are independent trees and are NOT affected.
import { usePatternManagementStore } from './patternManagementStore'

const store = usePatternManagementStore()
</script>

<template>
  <div v-if="store.deletingId" class="pb-pattern-dialog" role="alertdialog" aria-modal="true" aria-label="Delete pattern">
    <div class="pb-pattern-dialog__backdrop" @click="store.cancelDelete()" />
    <div class="pb-pattern-dialog__panel" tabindex="-1" @keydown.esc="store.cancelDelete()">
      <h3 class="pb-pattern-dialog__title">Delete pattern</h3>
      <p class="pb-pattern-dialog__body">
        Delete <strong>{{ store.deletingPattern?.name }}</strong>? This cannot be undone.
      </p>
      <p class="pb-pattern-dialog__hint">Components already inserted from this pattern are not affected.</p>
      <div class="pb-pattern-dialog__actions">
        <button type="button" class="pb-pattern-dialog__btn" @click="store.cancelDelete()">Cancel</button>
        <button type="button" class="pb-pattern-dialog__btn pb-pattern-dialog__btn--danger" @click="store.confirmDelete()">Delete</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-pattern-dialog { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-pattern-dialog__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-pattern-dialog__panel { position: relative; width: min(22rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-pattern-dialog__title { margin: 0; font-size: 0.95rem; font-weight: 600; }
.pb-pattern-dialog__body { margin: 0; font-size: 0.8rem; color: #334155; }
.pb-pattern-dialog__hint { margin: 0; font-size: 0.7rem; color: #94a3b8; }
.pb-pattern-dialog__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.4rem; }
.pb-pattern-dialog__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-pattern-dialog__btn:hover { background: #f8fafc; }
.pb-pattern-dialog__btn--danger { background: #dc2626; border-color: #dc2626; color: #fff; }
.pb-pattern-dialog__btn--danger:hover { background: #b91c1c; }
</style>
