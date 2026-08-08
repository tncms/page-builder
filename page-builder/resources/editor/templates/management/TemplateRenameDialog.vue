<script setup lang="ts">
// Phase 26.19 — the template rename dialog. A thin name form over the management store; the
// rename goes through the TemplateProvider (id and list position preserved).
import { useTemplateManagementStore } from './templateManagementStore'

const store = useTemplateManagementStore()

function onSubmit(): void {
  store.confirmRename()
}
</script>

<template>
  <div v-if="store.renamingId" class="pb-template-rename" role="dialog" aria-modal="true" aria-label="Rename template">
    <div class="pb-template-rename__backdrop" @click="store.cancelRename()" />
    <form class="pb-template-rename__panel" @submit.prevent="onSubmit" @keydown.esc="store.cancelRename()">
      <h3 class="pb-template-rename__title">Rename template</h3>

      <label class="pb-template-rename__field">
        <span class="pb-template-rename__label">Name</span>
        <input
          type="text"
          class="pb-template-rename__input"
          :value="store.renameDraft"
          maxlength="80"
          aria-required="true"
          @input="store.setRenameDraft(($event.target as HTMLInputElement).value)"
        />
        <span v-if="store.renameError" class="pb-template-rename__error">{{ store.renameError }}</span>
      </label>

      <div class="pb-template-rename__actions">
        <button type="button" class="pb-template-rename__btn" @click="store.cancelRename()">Cancel</button>
        <button type="submit" class="pb-template-rename__btn pb-template-rename__btn--primary">Save</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.pb-template-rename { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; }
.pb-template-rename__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-template-rename__panel { position: relative; width: min(22rem, 92vw); background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-template-rename__title { margin: 0 0 0.25rem; font-size: 0.95rem; font-weight: 600; }
.pb-template-rename__field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-template-rename__label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-template-rename__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-template-rename__input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-template-rename__error { font-size: 0.68rem; color: #dc2626; }
.pb-template-rename__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.35rem; }
.pb-template-rename__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-template-rename__btn:hover { background: #f8fafc; }
.pb-template-rename__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-template-rename__btn--primary:hover { background: #1d4ed8; }
</style>
