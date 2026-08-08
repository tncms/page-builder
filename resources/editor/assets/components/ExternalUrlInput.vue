<script setup lang="ts">
// Phase 26.20 — external URL source. A single URL input that only accepts a safe http(s)
// (or relative) URL. On every change it emits an `external` AssetReference when the URL is
// safe and non-empty, or null otherwise, so the picker's Insert stays disabled for unsafe
// input. It never stores anything but a URL — language-invariant, no id.
import { ref, watch } from 'vue'
import { externalAsset, type AssetReference } from '../contracts/AssetReference'
import { isSafeAssetUrl } from '../runtime/resolveAssetPreview'

const props = defineProps<{ modelValue?: string }>()
const emit = defineEmits<{ 'update:draft': [AssetReference | null] }>()

const url = ref(props.modelValue ?? '')

const invalid = ref(false)

function onInput(value: string): void {
  url.value = value
  const trimmed = value.trim()
  if (!trimmed) {
    invalid.value = false
    emit('update:draft', null)
    return
  }
  if (isSafeAssetUrl(trimmed)) {
    invalid.value = false
    emit('update:draft', externalAsset(trimmed))
  } else {
    invalid.value = true
    emit('update:draft', null)
  }
}

// Seed from an incoming value (e.g. the field's current URL) once.
watch(
  () => props.modelValue,
  (value) => {
    if (value != null && value !== url.value) onInput(value)
  },
)
</script>

<template>
  <div class="pb-asset-url">
    <label class="pb-asset-url__field">
      <span class="pb-asset-url__label">Image URL</span>
      <input
        type="url"
        class="pb-asset-url__input"
        :value="url"
        placeholder="https://…"
        inputmode="url"
        @input="onInput(($event.target as HTMLInputElement).value)"
      />
    </label>
    <p v-if="invalid" class="pb-asset-url__error">Enter a valid http(s) URL.</p>
  </div>
</template>

<style scoped>
.pb-asset-url { display: flex; flex-direction: column; gap: 0.35rem; }
.pb-asset-url__field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-asset-url__label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-asset-url__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-asset-url__input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-asset-url__error { margin: 0; font-size: 0.68rem; color: #dc2626; }
</style>
