<script setup lang="ts">
// Phase 26.14 — rename a local pattern. Metadata only: it updates the registry entry in
// place (id + tree preserved), never touching the component tree or editor history.
import { usePatternManagementStore } from './patternManagementStore'

const store = usePatternManagementStore()

function onSubmit(): void {
  store.confirmRename()
}
</script>

<template>
  <div v-if="store.renamingId" class="pb-pattern-dialog" role="dialog" aria-modal="true" aria-label="Rename pattern">
    <div class="pb-pattern-dialog__backdrop" @click="store.cancelRename()" />
    <form class="pb-pattern-dialog__panel" @submit.prevent="onSubmit" @keydown.esc="store.cancelRename()">
      <h3 class="pb-pattern-dialog__title">Rename pattern</h3>
      <label class="pb-pattern-dialog__field">
        <span class="pb-pattern-dialog__label">Name</span>
        <input
          type="text"
          class="pb-pattern-dialog__input"
          :value="store.renameDraft"
          maxlength="80"
          aria-required="true"
          @input="store.setRenameDraft(($event.target as HTMLInputElement).value)"
        />
        <span v-if="store.renameError" class="pb-pattern-dialog__error">{{ store.renameError }}</span>
      </label>
      <div class="pb-pattern-dialog__actions">
        <button type="button" class="pb-pattern-dialog__btn" @click="store.cancelRename()">Cancel</button>
        <button type="submit" class="pb-pattern-dialog__btn pb-pattern-dialog__btn--primary">Save</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.pb-pattern-dialog { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-pattern-dialog__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-pattern-dialog__panel { position: relative; width: min(22rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-pattern-dialog__title { margin: 0 0 0.25rem; font-size: 0.95rem; font-weight: 600; }
.pb-pattern-dialog__field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-pattern-dialog__label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-pattern-dialog__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-pattern-dialog__input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-pattern-dialog__error { font-size: 0.68rem; color: #dc2626; }
.pb-pattern-dialog__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.35rem; }
.pb-pattern-dialog__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-pattern-dialog__btn:hover { background: #f8fafc; }
.pb-pattern-dialog__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-dialog__btn--primary:hover { background: #1d4ed8; }
</style>
