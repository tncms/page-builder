<script setup lang="ts">
// Phase 26.20 — Asset Source Picker dialog. Mounted once in the editor shell, it registers
// itself into the `mediaPicker` seam so every `type: 'media'` field can open it. It offers two
// sources — the CMS Media Library and an external URL — plus a shared alt field and a live
// preview. Confirm returns the existing `{ src, alt? }` result to the field (no new format);
// the field commits it through the normal Apply/undo path.
import { onMounted, onUnmounted } from 'vue'
import { registerMediaPicker } from '../../components/inspector/mediaPicker'
import { useAssetPickerStore } from '../assetPickerStore'
import MediaLibraryBrowser from './MediaLibraryBrowser.vue'
import ExternalUrlInput from './ExternalUrlInput.vue'
import AssetPreview from './AssetPreview.vue'

const store = useAssetPickerStore()

onMounted(() => {
  registerMediaPicker((request) => store.openPicker(request))
})
onUnmounted(() => {
  registerMediaPicker(null)
})
</script>

<template>
  <div v-if="store.open" class="pb-asset-picker" role="dialog" aria-modal="true" aria-label="Choose media">
    <div class="pb-asset-picker__backdrop" @click="store.cancel()" />
    <div class="pb-asset-picker__panel" @keydown.esc="store.cancel()">
      <h3 class="pb-asset-picker__title">Choose media</h3>

      <div class="pb-asset-picker__tabs" role="tablist">
        <button
          type="button"
          class="pb-asset-picker__tab"
          :class="{ 'is-active': store.source === 'library' }"
          role="tab"
          :aria-selected="store.source === 'library'"
          @click="store.setSource('library')"
        >
          Media Library
        </button>
        <button
          type="button"
          class="pb-asset-picker__tab"
          :class="{ 'is-active': store.source === 'url' }"
          role="tab"
          :aria-selected="store.source === 'url'"
          @click="store.setSource('url')"
        >
          External URL
        </button>
      </div>

      <div class="pb-asset-picker__body">
        <MediaLibraryBrowser
          v-if="store.source === 'library'"
          :selected-id="store.draft?.id ?? null"
          @select="store.setDraft($event)"
        />
        <ExternalUrlInput
          v-else
          :model-value="store.request?.current ?? ''"
          @update:draft="store.setDraft($event)"
        />
      </div>

      <label class="pb-asset-picker__alt">
        <span class="pb-asset-picker__alt-label">Alt text <em>(optional)</em></span>
        <input
          type="text"
          class="pb-asset-picker__alt-input"
          :value="store.alt"
          placeholder="Describe the image"
          @input="store.setAlt(($event.target as HTMLInputElement).value)"
        />
      </label>

      <AssetPreview :value="store.draft" />

      <div class="pb-asset-picker__actions">
        <button type="button" class="pb-asset-picker__btn" @click="store.cancel()">Cancel</button>
        <button
          type="button"
          class="pb-asset-picker__btn pb-asset-picker__btn--primary"
          :disabled="!store.canConfirm"
          @click="store.confirm()"
        >
          Insert
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-asset-picker { position: fixed; inset: 0; z-index: 70; display: flex; align-items: center; justify-content: center; }
.pb-asset-picker__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.pb-asset-picker__panel { position: relative; width: min(34rem, 94vw); max-height: 88vh; overflow-y: auto; background: #fff; border-radius: 0.6rem; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.7rem; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.25); }
.pb-asset-picker__title { margin: 0; font-size: 0.95rem; font-weight: 600; }
.pb-asset-picker__tabs { display: flex; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; }
.pb-asset-picker__tab { border: none; background: none; padding: 0.4rem 0.6rem; font-size: 0.78rem; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; }
.pb-asset-picker__tab.is-active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }
.pb-asset-picker__body { min-height: 6rem; }
.pb-asset-picker__alt { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-asset-picker__alt-label { font-size: 0.72rem; color: #475569; font-weight: 500; }
.pb-asset-picker__alt-label em { color: #94a3b8; font-style: normal; }
.pb-asset-picker__alt-input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-asset-picker__alt-input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-asset-picker__actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.15rem; }
.pb-asset-picker__btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.76rem; cursor: pointer; }
.pb-asset-picker__btn:hover { background: #f8fafc; }
.pb-asset-picker__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-asset-picker__btn--primary:hover:not(:disabled) { background: #1d4ed8; }
.pb-asset-picker__btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
