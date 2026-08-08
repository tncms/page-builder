<script setup lang="ts">
// Phase 26.35 — import/export controls + result dialog for the workspace.
//
// Export downloads the visible results as a package (reuses Phase 26.26); import reads an uploaded
// JSON file and runs the atomic import + compatibility gate, then shows a success/error dialog. It
// never applies templates or touches BuilderState — import lands templates in the imported library.
import { useTemplateWorkspaceStore } from './templateWorkspaceStore'

const store = useTemplateWorkspaceStore()

/** Trigger a browser download of the last export artifact. */
function download(): void {
  const result = store.exportResults({ id: 'template-library', name: 'Template Library' })
  if (!result.ok || !result.json || !result.filename) return
  const blob = new Blob([result.json], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = result.filename
  anchor.click()
  URL.revokeObjectURL(url)
}

/** Read an uploaded file and import it. */
function onFile(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    store.importPackage(String(reader.result ?? ''))
    input.value = '' // allow re-uploading the same file
  }
  reader.readAsText(file)
}
</script>

<template>
  <div class="pb-tpl-xfer">
    <div class="pb-tpl-xfer__actions">
      <button
        type="button"
        class="pb-tpl-xfer__btn"
        :disabled="store.results.length === 0"
        @click="download"
      >
        Export ({{ store.results.length }})
      </button>
      <label class="pb-tpl-xfer__btn pb-tpl-xfer__btn--import">
        Import
        <input type="file" accept="application/json,.json" class="pb-tpl-xfer__file" @change="onFile" />
      </label>
    </div>

    <div
      v-if="store.importOutcome"
      class="pb-tpl-xfer__dialog"
      :class="store.importOutcome.ok ? 'is-ok' : 'is-error'"
      role="status"
    >
      <template v-if="store.importOutcome.ok">
        <strong>Imported {{ store.importOutcome.templateIds?.length ?? 0 }} template(s).</strong>
        <span v-if="store.importOutcome.warnings?.length" class="pb-tpl-xfer__warn">
          {{ store.importOutcome.warnings.length }} compatibility warning(s).
        </span>
      </template>
      <template v-else>
        <strong>{{ store.importOutcome.error?.message }}</strong>
        <ul class="pb-tpl-xfer__issues">
          <li v-for="(issue, i) in store.importOutcome.error?.issues ?? []" :key="i">{{ issue.message }}</li>
        </ul>
      </template>
      <button type="button" class="pb-tpl-xfer__dismiss" @click="store.clearTransfer()">Dismiss</button>
    </div>
  </div>
</template>

<style scoped>
.pb-tpl-xfer {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.pb-tpl-xfer__actions {
  display: flex;
  gap: 0.4rem;
}
.pb-tpl-xfer__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.7rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.4rem;
  background: #fff;
  font-size: 0.72rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}
.pb-tpl-xfer__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pb-tpl-xfer__btn--import {
  position: relative;
}
.pb-tpl-xfer__file {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.pb-tpl-xfer__dialog {
  padding: 0.6rem 0.75rem;
  border-radius: 0.4rem;
  font-size: 0.72rem;
  border: 1px solid;
}
.pb-tpl-xfer__dialog.is-ok {
  border-color: rgba(22, 163, 74, 0.4);
  background: rgba(22, 163, 74, 0.08);
  color: #15803d;
}
.pb-tpl-xfer__dialog.is-error {
  border-color: rgba(220, 38, 38, 0.4);
  background: rgba(220, 38, 38, 0.08);
  color: #b91c1c;
}
.pb-tpl-xfer__issues {
  margin: 0.35rem 0 0;
  padding-left: 1rem;
}
.pb-tpl-xfer__warn {
  display: block;
  margin-top: 0.2rem;
  color: #b45309;
}
.pb-tpl-xfer__dismiss {
  margin-top: 0.4rem;
  padding: 0.2rem 0.5rem;
  border: 0;
  border-radius: 0.3rem;
  background: rgba(15, 23, 42, 0.08);
  font-size: 0.68rem;
  cursor: pointer;
}
</style>
