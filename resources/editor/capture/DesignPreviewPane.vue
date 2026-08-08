<script setup lang="ts">
// Phase 26.48 — live preview of the selection being saved (Rule 4). It REUSES the
// existing editor render pipeline (store.client.renderPreview → LayoutRenderer);
// it never screenshots and never introduces a second renderer. The captured node
// is wrapped exactly as the pipeline normalizes it (a block in a neutral host
// section) so the preview matches what will be saved. Fails gracefully.
import { onMounted, ref } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { LayoutPayload } from '../types'

const props = defineProps<{ node: Record<string, unknown>; kind: string }>()
const store = useEditorStore()
const html = ref('')
const loading = ref(true)
const failed = ref(false)

function wrapped(): LayoutPayload {
  const section = props.kind === 'section' ? props.node : { type: 'generic', data: {}, blocks: [props.node] }
  return { schema: 'pagebuilder/01', sections: [section] } as unknown as LayoutPayload
}

onMounted(async () => {
  try {
    if (!store.client || !store.owner) {
      failed.value = true
      return
    }
    html.value = await store.client.renderPreview(wrapped(), store.owner.type, store.owner.id)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="pb-cap-preview" data-testid="design-preview-pane">
    <div v-if="loading" class="pb-cap-preview__state">Generating preview…</div>
    <div v-else-if="failed || !html" class="pb-cap-preview__state">Preview will be available after saving.</div>
    <iframe
      v-else
      :srcdoc="html"
      title="Design preview"
      sandbox="allow-same-origin"
      data-testid="capture-preview-frame"
    ></iframe>
  </div>
</template>

<style scoped>
.pb-cap-preview { background: #fff; border: 1px solid var(--pb-border, #262b36); border-radius: 10px; overflow: hidden; min-height: 220px; display: grid; }
.pb-cap-preview iframe { width: 100%; height: 100%; min-height: 220px; border: 0; }
.pb-cap-preview__state { display: grid; place-items: center; color: #94a3b8; font-size: 0.8rem; min-height: 220px; }
</style>
