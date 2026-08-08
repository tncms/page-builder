<script setup lang="ts">
// Phase 26.20 — CMS Media Library browser. Fetches images from the existing admin search
// endpoint via `searchMediaLibrary` and renders a selectable grid with loading / error /
// empty states. Selecting an item emits a `media` AssetReference carrying the item's URL
// (stored) and its id/alt (display only). No upload, no crop, no infinite scroll.
import { onMounted, ref } from 'vue'
import { searchMediaLibrary, type MediaLibraryItem } from '../mediaLibraryClient'
import { mediaAsset, type AssetReference } from '../contracts/AssetReference'

const props = defineProps<{ selectedId?: number | null }>()
const emit = defineEmits<{ select: [AssetReference] }>()

const items = ref<MediaLibraryItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const query = ref('')

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    items.value = await searchMediaLibrary({ q: query.value.trim() })
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not load media.'
    items.value = []
  } finally {
    loading.value = false
  }
}

function choose(item: MediaLibraryItem): void {
  emit('select', mediaAsset(item.url, { id: item.id, alt: item.alt }))
}

onMounted(load)
</script>

<template>
  <div class="pb-media-lib">
    <form class="pb-media-lib__search" @submit.prevent="load">
      <input
        type="search"
        class="pb-media-lib__search-input"
        :value="query"
        placeholder="Search media…"
        aria-label="Search media library"
        @input="query = ($event.target as HTMLInputElement).value"
      />
      <button type="submit" class="pb-media-lib__search-btn">Search</button>
    </form>

    <p v-if="loading" class="pb-media-lib__state">Loading media…</p>
    <p v-else-if="error" class="pb-media-lib__state pb-media-lib__state--error">
      {{ error }}
      <button type="button" class="pb-media-lib__retry" @click="load">Retry</button>
    </p>
    <p v-else-if="items.length === 0" class="pb-media-lib__state">No media found.</p>

    <ul v-else class="pb-media-lib__grid">
      <li v-for="item in items" :key="item.id">
        <button
          type="button"
          class="pb-media-lib__item"
          :class="{ 'is-selected': item.id === props.selectedId }"
          :title="item.name"
          @click="choose(item)"
        >
          <img :src="item.url" :alt="item.alt" class="pb-media-lib__thumb" loading="lazy" />
          <span class="pb-media-lib__name">{{ item.name }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.pb-media-lib { display: flex; flex-direction: column; gap: 0.5rem; }
.pb-media-lib__search { display: flex; gap: 0.35rem; }
.pb-media-lib__search-input { flex: 1; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-media-lib__search-input:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
.pb-media-lib__search-btn { border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.4rem 0.7rem; font-size: 0.76rem; cursor: pointer; }
.pb-media-lib__search-btn:hover { background: #f8fafc; }
.pb-media-lib__state { margin: 0.5rem 0; font-size: 0.72rem; color: #94a3b8; }
.pb-media-lib__state--error { color: #dc2626; }
.pb-media-lib__retry { margin-left: 0.4rem; border: 1px solid #fecaca; border-radius: 0.35rem; background: #fff; padding: 0.15rem 0.45rem; font-size: 0.68rem; color: #dc2626; cursor: pointer; }
.pb-media-lib__grid { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(6rem, 1fr)); gap: 0.5rem; max-height: 18rem; overflow-y: auto; }
.pb-media-lib__item { display: flex; flex-direction: column; gap: 0.25rem; width: 100%; border: 1px solid #e2e8f0; border-radius: 0.45rem; background: #fff; padding: 0.3rem; cursor: pointer; }
.pb-media-lib__item:hover { border-color: #93c5fd; }
.pb-media-lib__item.is-selected { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
.pb-media-lib__thumb { width: 100%; height: 4.5rem; object-fit: cover; border-radius: 0.3rem; background: #f1f5f9; }
.pb-media-lib__name { font-size: 0.64rem; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
