<script setup lang="ts">
// Phase 26.47F — Type + Source badges for the Builder Resource Inspector.
// Type (SECTION / BLOCK / KIT) and source group (Built-in / Imported; Marketplace
// / My Designs / PRO reserved) use distinct styles built from existing tokens.
import { computed } from 'vue'

const props = defineProps<{ kind: string; sourceGroup: string }>()

const typeLabel = computed(() => props.kind.toUpperCase())

const SOURCE_LABELS: Record<string, string> = {
  builtin: 'Built-in',
  imported: 'Imported',
  'my-designs': 'My Designs',
  marketplace: 'Marketplace',
  favorites: 'Favorites',
}

const sourceLabel = computed(
  () => SOURCE_LABELS[props.sourceGroup] ?? props.sourceGroup.replace(/\b\w/g, (c) => c.toUpperCase()),
)
</script>

<template>
  <div class="pb-rb" data-testid="resource-badges">
    <span class="pb-rb__badge pb-rb__type" :class="`pb-rb__type--${kind}`" data-testid="type-badge">
      {{ typeLabel }}
    </span>
    <span class="pb-rb__badge pb-rb__source" :class="`pb-rb__source--${sourceGroup}`" data-testid="source-badge">
      {{ sourceLabel }}
    </span>
  </div>
</template>

<style scoped>
.pb-rb { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.pb-rb__badge {
  font-size: 0.62rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
  padding: 0.15rem 0.5rem; border-radius: 999px; border: 1px solid var(--pb-border, #262b36); line-height: 1.4;
}
.pb-rb__type { color: var(--pb-accent, #6ea8fe); border-color: color-mix(in oklab, var(--pb-accent, #6ea8fe), transparent 60%); }
.pb-rb__type--block { color: #7ee0a8; border-color: color-mix(in oklab, #7ee0a8, transparent 60%); }
.pb-rb__type--kit { color: #ffcf70; border-color: color-mix(in oklab, #ffcf70, transparent 60%); }
.pb-rb__source { color: var(--pb-editor-text-muted, #9aa4b2); }
.pb-rb__source--imported { color: #c9a2ff; border-color: color-mix(in oklab, #c9a2ff, transparent 60%); }
</style>
