<script setup lang="ts">
// PB-TEMPLATE-REGISTRY-2-D — the ONE shared, presentation-only placeholder for an unknown
// stored component (a node whose type resolves to no registered definition). It NEVER renders
// or interprets the node's data: it states the component is unavailable, shows the safe type
// identifier as escaped text, and states the content is preserved. Used by the Canvas node
// components and the Property Inspector so the message + safety story live in one place.
// Icon + text convey status (not color alone). No v-html, no data dump, no delete/convert.
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'

const props = withDefaults(
  defineProps<{ type: string; kind: string; variant?: 'canvas' | 'inspector' }>(),
  { variant: 'canvas' },
)

const store = useEditorStore()
const s = (key: string, fallback: string): string => store.uiString(key, fallback)

const heading = computed(() => s('unknownHeading', 'Component unavailable'))
const typeLabel = computed(() => s('unknownType', 'Component type'))
const preserved = computed(() => s('unknownPreserved', 'This content is preserved and will be saved unchanged.'))
const reason = computed(() => s('unknownReason', 'This component is not currently available in this site.'))
const inspectorNote = computed(() => s('unknownInspectorNote', 'Editing controls will return when this component is available again.'))
</script>

<template>
  <div
    class="pb-unknown"
    :class="`pb-unknown--${variant}`"
    role="note"
    :aria-label="`${heading}: ${type}`"
    data-testid="unknown-placeholder"
  >
    <p class="pb-unknown__head">
      <span class="pb-unknown__icon" aria-hidden="true">⚠</span>
      <span class="pb-unknown__title">{{ heading }}</span>
    </p>
    <p class="pb-unknown__type">
      <span class="pb-unknown__type-label">{{ typeLabel }}:</span>
      <code class="pb-unknown__type-id">{{ type }}</code>
    </p>
    <p class="pb-unknown__reason">{{ reason }}</p>
    <p class="pb-unknown__preserved">{{ preserved }}</p>
    <p v-if="variant === 'inspector'" class="pb-unknown__note">{{ inspectorNote }}</p>
  </div>
</template>

<style scoped>
.pb-unknown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border: 1px dashed var(--pb-border, #b58a2a);
  border-radius: 8px;
  background: color-mix(in srgb, #f5b301 12%, transparent);
  color: var(--pb-editor-text, inherit);
}
.pb-unknown--canvas { margin: 4px 0; }
.pb-unknown__head { display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 700; }
.pb-unknown__icon { font-size: 1.05rem; line-height: 1; }
.pb-unknown__title { overflow-wrap: anywhere; }
.pb-unknown__type { margin: 0; font-size: 0.85em; display: flex; flex-wrap: wrap; gap: 6px; align-items: baseline; }
.pb-unknown__type-label { opacity: 0.75; }
.pb-unknown__type-id { overflow-wrap: anywhere; word-break: break-all; }
.pb-unknown__reason,
.pb-unknown__preserved,
.pb-unknown__note { margin: 0; font-size: 0.85em; opacity: 0.9; overflow-wrap: anywhere; }
.pb-unknown__note { opacity: 0.75; font-style: italic; }
@media (prefers-reduced-motion: reduce) { .pb-unknown * { transition: none !important; animation: none !important; } }
</style>
