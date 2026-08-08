<script setup lang="ts">
// Phase 26.5 — Media editor: preview, choose/replace via a pluggable picker seam,
// remove/reset, and optional alt text.
//
// Value shape is backward-compatible: a plain string (media id/URL) by default, so
// existing `media` fields are untouched. A field opts into inline alt editing with
// `editor.alt: true`, in which case the value becomes `{ src, alt }`. When no host
// picker is registered (see mediaPicker.ts) the editor falls back to a manual input.
import { computed, ref } from 'vue'
import type { SchemaField } from '../schema'
import { resolveMediaPicker, hasMediaPicker } from '../mediaPicker'

const props = defineProps<{ modelValue: unknown; field?: SchemaField }>()
const emit = defineEmits<{ update: [value: unknown]; commit: []; revert: [] }>()

const editorMeta = computed(() => (props.field?.editor ?? {}) as { alt?: boolean; accept?: string[]; multiple?: boolean })
const altEnabled = computed(() => editorMeta.value.alt === true)

const src = computed<string>(() => {
  const v = props.modelValue
  if (altEnabled.value) return (v as { src?: string })?.src ?? ''
  return typeof v === 'string' ? v : ''
})
const alt = computed<string>(() =>
  altEnabled.value ? ((props.modelValue as { alt?: string })?.alt ?? '') : '',
)

// Compose the outgoing value in the shape this field uses.
function emitValue(nextSrc: string, nextAlt: string): void {
  emit('update', altEnabled.value ? { src: nextSrc, alt: nextAlt } : nextSrc)
}

const pickerAvailable = hasMediaPicker()
const broken = ref(false)

function onSrc(v: string): void {
  broken.value = false
  emitValue(v, alt.value)
}
function onAlt(v: string): void {
  emitValue(src.value, v)
}

async function choose(): Promise<void> {
  const picker = resolveMediaPicker()
  if (!picker) return
  const result = await picker({
    accept: editorMeta.value.accept,
    multiple: editorMeta.value.multiple,
    current: src.value,
  })
  if (!result) return // cancelled
  broken.value = false
  emitValue(result.src, altEnabled.value ? (result.alt ?? alt.value) : alt.value)
  emit('commit')
}

function remove(): void {
  broken.value = false
  emitValue('', '')
  emit('commit')
}
</script>

<template>
  <div class="pb-inspector-media">
    <div v-if="src" class="pb-inspector-media__preview">
      <img v-if="!broken" :src="src" alt="" @error="broken = true" />
      <span v-else class="pb-inspector-media__noimg" :title="src">No preview</span>
    </div>

    <div class="pb-inspector-media__actions">
      <button v-if="pickerAvailable" type="button" class="pb-ui-btn pb-editor-btn" @click="choose">
        {{ src ? 'Replace' : 'Choose media' }}
      </button>
      <button v-if="src" type="button" class="pb-ui-btn pb-editor-btn pb-editor-btn--danger" @click="remove">Remove</button>
    </div>

    <input
      type="text"
      class="pb-inspector-input"
      :value="src"
      placeholder="Media id or URL"
      @input="onSrc(($event.target as HTMLInputElement).value)"
      @blur="emit('commit')"
      @keydown.enter.prevent="emit('commit')"
      @keydown.esc.prevent="emit('revert')"
    />

    <label v-if="altEnabled" class="pb-inspector-media__alt">
      <span>Alt text</span>
      <input
        type="text"
        class="pb-inspector-input"
        :value="alt"
        placeholder="Describe the image"
        @input="onAlt(($event.target as HTMLInputElement).value)"
        @blur="emit('commit')"
        @keydown.enter.prevent="emit('commit')"
        @keydown.esc.prevent="emit('revert')"
      />
    </label>

    <p v-if="!pickerAvailable" class="pb-inspector-hint">
      Enter a media id or URL. A media picker can be wired in via the picker seam.
    </p>
  </div>
</template>
