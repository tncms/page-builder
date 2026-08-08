<script setup lang="ts">
// Phase 26.5 — Link editor. Edits a { url, newTab } object.
//
// Hard validation (URL well-formedness / required) is enforced centrally by
// `fieldValidation.ts`, which gates Apply — this component only surfaces a soft,
// non-blocking hint while typing. `nofollow` / `sponsored` / `download` are future
// attributes that add keys to this same object without a shape break.
import { computed } from 'vue'

type Link = { url?: unknown; newTab?: unknown }

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: Link]; commit: []; revert: [] }>()

const current = computed<Link>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Link)
    : {},
)

const url = computed(() => (current.value.url as string) ?? '')
const newTab = computed(() => Boolean(current.value.newTab))

// Soft hint only — never blocks editing (the Apply gate owns real validation).
const warn = computed(() => url.value !== '' && /\s/.test(url.value))

function setUrl(value: string): void {
  emit('update', { ...current.value, url: value })
}
function setNewTab(value: boolean): void {
  emit('update', { ...current.value, newTab: value })
  emit('commit')
}
</script>

<template>
  <div class="pb-inspector-link">
    <input
      type="text"
      inputmode="url"
      class="pb-inspector-input"
      placeholder="https://…"
      :value="url"
      @input="setUrl(($event.target as HTMLInputElement).value)"
      @blur="emit('commit')"
      @keydown.enter.prevent="emit('commit')"
      @keydown.esc.prevent="emit('revert')"
    />
    <label class="pb-inspector-link__toggle">
      <input type="checkbox" :checked="newTab" @change="setNewTab(($event.target as HTMLInputElement).checked)" />
      <span>Open in new tab</span>
    </label>
    <p v-if="warn" class="pb-inspector-warn">URL contains spaces — this may not link correctly.</p>
  </div>
</template>
