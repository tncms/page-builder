<script setup lang="ts">
// Phase 26.48 — Save as Design dialog. Captures the selected section/block through
// the server Design Capture Pipeline (an external persistence action). On success
// it ONLY closes the dialog (Rule 13): it never dispatches a command, never mutates
// the payload/selection, and never touches undo/redo history (Rule 20). Duplicate
// handling offers Create Copy; Update Existing is reserved (Rule 9).
import { computed, reactive, ref } from 'vue'
import { useEditorStore } from '../stores/editor'
import DesignMetadataForm from './DesignMetadataForm.vue'
import DesignPreviewPane from './DesignPreviewPane.vue'

const store = useEditorStore()
const target = computed(() => store.captureTarget)
const form = reactive({ name: '', description: '', category: '', tags: '' })
const saving = ref(false)
const error = ref('')
const duplicate = ref(false)

function close() {
  // Rule 13 — closing the dialog is the ONLY side effect. Nothing else changes.
  duplicate.value = false
  error.value = ''
  store.closeCapture()
}

async function submit(forceCopy = false) {
  const current = target.value
  if (!current || !store.client || saving.value) return
  saving.value = true
  error.value = ''
  try {
    const { data } = await store.client.captureDesign({
      node: current.node,
      kind: current.kind,
      name: form.name,
      description: form.description,
      category: form.category,
      tags: form.tags.split(',').map((t) => t.trim()).filter(Boolean),
      mode: forceCopy ? 'copy' : undefined,
    })
    if (data.status === 'saved') {
      close()
    } else if (data.status === 'duplicate') {
      duplicate.value = true
    } else {
      error.value = (data.issues ?? []).map((i) => i.message).join(' ') || 'Could not save the design.'
    }
  } catch {
    error.value = 'Could not save the design.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div
    v-if="target"
    class="pb-cap"
    role="dialog"
    aria-modal="true"
    aria-label="Save as Design"
    data-testid="save-as-design-dialog"
    @click.self="close"
  >
    <div class="pb-cap__box" tabindex="-1" @keydown.esc="close">
      <header class="pb-cap__head">
        <strong>Save as Design</strong>
        <button type="button" class="pb-cap__icon" aria-label="Cancel" data-testid="close" @click="close">×</button>
      </header>

      <div class="pb-cap__body">
        <DesignPreviewPane :node="target.node" :kind="target.kind" />
        <div class="pb-cap__side">
          <DesignMetadataForm
            v-model:name="form.name"
            v-model:description="form.description"
            v-model:category="form.category"
            v-model:tags="form.tags"
          />
          <p v-if="error" class="pb-cap__error" data-testid="cap-error">{{ error }}</p>

          <div v-if="duplicate" class="pb-cap__dupe" data-testid="duplicate">
            <p>Resource already exists.</p>
            <div class="pb-cap__dupe-actions">
              <button type="button" @click="close">Cancel</button>
              <button type="button" data-testid="create-copy" @click="submit(true)">Create Copy</button>
              <button type="button" disabled title="Reserved">Update Existing</button>
            </div>
          </div>
        </div>
      </div>

      <footer v-if="!duplicate" class="pb-cap__foot">
        <button type="button" data-testid="cancel" @click="close">Cancel</button>
        <button
          type="button"
          class="pb-cap__primary"
          :disabled="saving || form.name.trim() === ''"
          data-testid="save"
          @click="submit(false)"
        >
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
/* PB-TEMPLATE-REGISTRY-2-H1 — Save as Design stays the top-priority dialog, above the modal band. */
.pb-cap { position: fixed; inset: 0; z-index: var(--pb-ui-z-modal-top); display: grid; place-items: center; padding: 2rem; background: rgba(6, 8, 12, 0.6); }
.pb-cap__box { width: min(760px, 100%); max-height: 90vh; display: flex; flex-direction: column; background: var(--pb-editor-surface, #171a21); border: 1px solid var(--pb-border, #262b36); border-radius: 12px; outline: none; }
.pb-cap__head { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-bottom: 1px solid var(--pb-border, #262b36); color: var(--pb-editor-text, #e7eaf0); }
.pb-cap__icon { border: 1px solid var(--pb-border, #262b36); background: var(--pb-panel, #0f1115); color: var(--pb-editor-text, #e7eaf0); width: 1.8rem; height: 1.8rem; border-radius: 8px; font-size: 1.1rem; cursor: pointer; }
.pb-cap__body { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem; padding: 1rem; overflow: auto; }
.pb-cap__side { display: flex; flex-direction: column; gap: 0.75rem; }
.pb-cap__error { margin: 0; color: #ff9aa8; font-size: 0.8rem; }
.pb-cap__dupe { border: 1px solid #6b5a25; border-radius: 8px; padding: 0.6rem 0.75rem; color: #ffcf70; font-size: 0.82rem; }
.pb-cap__dupe-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
.pb-cap__dupe-actions button { border: 1px solid var(--pb-border, #262b36); background: var(--pb-panel, #0f1115); color: var(--pb-editor-text, #e7eaf0); border-radius: 8px; padding: 0.4rem 0.7rem; font: inherit; cursor: pointer; }
.pb-cap__dupe-actions button:disabled { opacity: 0.5; cursor: default; }
.pb-cap__foot { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.85rem 1rem; border-top: 1px solid var(--pb-border, #262b36); }
.pb-cap__foot button { border: 1px solid var(--pb-border, #262b36); background: var(--pb-editor-surface, #171a21); color: var(--pb-editor-text, #e7eaf0); border-radius: 8px; padding: 0.55rem 1rem; font: inherit; cursor: pointer; }
.pb-cap__primary { background: var(--pb-accent, #6ea8fe); color: #0b1220; border-color: transparent; font-weight: 600; }
.pb-cap__foot button:disabled { opacity: 0.6; cursor: default; }
@media (max-width: 640px) { .pb-cap__body { grid-template-columns: 1fr; } }
</style>
