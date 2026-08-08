<script setup lang="ts">
// Phase 26.20 — asset preview. Renders a safe thumbnail for any stored media value (URL
// string, `{ src, alt }`, or an AssetReference) via the pure `resolveAssetPreview`. Shows a
// distinct state for empty, blocked (unsafe URL), and load-error.
import { computed, ref, watch } from 'vue'
import { resolveAssetPreview } from '../runtime/resolveAssetPreview'

const props = defineProps<{ value: unknown }>()

const broken = ref(false)
const preview = computed(() => resolveAssetPreview(props.value))

// Reset the broken flag whenever the source changes.
watch(
  () => preview.value.src,
  () => {
    broken.value = false
  },
)
</script>

<template>
  <div class="pb-asset-preview">
    <img
      v-if="preview.src && !broken"
      :src="preview.src"
      :alt="preview.alt"
      class="pb-asset-preview__img"
      @error="broken = true"
    />
    <span v-else-if="!preview.safe" class="pb-asset-preview__state pb-asset-preview__state--blocked">Blocked URL</span>
    <span v-else-if="preview.empty" class="pb-asset-preview__state">No media selected</span>
    <span v-else class="pb-asset-preview__state">No preview</span>
  </div>
</template>

<style scoped>
.pb-asset-preview { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 6rem; border: 1px dashed #cbd5e1; border-radius: 0.45rem; background: #f8fafc; overflow: hidden; }
.pb-asset-preview__img { max-width: 100%; max-height: 12rem; object-fit: contain; }
.pb-asset-preview__state { font-size: 0.72rem; color: #94a3b8; }
.pb-asset-preview__state--blocked { color: #dc2626; }
</style>
