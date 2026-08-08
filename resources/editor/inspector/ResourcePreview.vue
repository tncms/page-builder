<script setup lang="ts">
// Phase 26.47F — the Inspector's large preview. Rule 4: reuse the existing Runtime
// Preview engine (an iframe on the preview route); never render or screenshot the
// Builder. Rule 14: lazy — the iframe only exists while the Inspector is open and
// its src changes per resource, so nothing is preloaded for the whole palette.
defineProps<{ src: string; name: string; device: 'desktop' | 'tablet' | 'mobile' }>()
defineEmits<{ 'update:device': ['desktop' | 'tablet' | 'mobile'] }>()

const DEVICES: Array<{ key: 'desktop' | 'tablet' | 'mobile'; label: string }> = [
  { key: 'desktop', label: 'Desktop' },
  { key: 'tablet', label: 'Tablet' },
  { key: 'mobile', label: 'Mobile' },
]
</script>

<template>
  <div class="pb-rp" data-testid="resource-preview">
    <div class="pb-rp__bar" role="group" aria-label="Preview device">
      <button
        v-for="d in DEVICES"
        :key="d.key"
        type="button"
        class="pb-rp__device"
        :aria-pressed="device === d.key"
        @click="$emit('update:device', d.key)"
      >
        {{ d.label }}
      </button>
    </div>
    <div class="pb-rp__frame" :class="`pb-rp__frame--${device}`">
      <iframe
        v-if="src"
        :src="src"
        :title="`Preview of ${name}`"
        loading="lazy"
        sandbox="allow-same-origin"
        data-testid="preview-frame"
      ></iframe>
      <div v-else class="pb-rp__empty">Preview unavailable</div>
    </div>
  </div>
</template>

<style scoped>
.pb-rp { display: flex; flex-direction: column; gap: 0.5rem; min-height: 0; }
.pb-rp__bar { display: flex; gap: 0.3rem; }
.pb-rp__device {
  border: 1px solid var(--pb-border, #262b36); background: var(--pb-editor-surface, #171a21);
  color: var(--pb-editor-text-muted, #9aa4b2); border-radius: 8px; padding: 0.35rem 0.7rem;
  font: inherit; font-size: 0.75rem; cursor: pointer;
}
.pb-rp__device[aria-pressed='true'] { background: var(--pb-accent, #6ea8fe); color: #0b1220; border-color: transparent; }
.pb-rp__frame { flex: 1; min-height: 260px; background: #fff; border: 1px solid var(--pb-border, #262b36); border-radius: 10px; overflow: hidden; display: grid; }
.pb-rp__frame iframe { width: 100%; height: 100%; min-height: 260px; border: 0; }
.pb-rp__frame--tablet iframe { max-width: 768px; margin: 0 auto; }
.pb-rp__frame--mobile iframe { max-width: 390px; margin: 0 auto; }
.pb-rp__empty { display: grid; place-items: center; color: #94a3b8; font-size: 0.8rem; min-height: 260px; }
</style>
