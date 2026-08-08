<script setup lang="ts">
// Phase 24.2 — editor-local Inspector field search. Filters which fields render;
// never touches the schema or payload. Two-way bound to the parent's query.
defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div class="pb-inspector-search">
    <label class="pb-inspector-search__label" for="pb-inspector-search-input">Search settings</label>
    <div class="pb-inspector-search__control">
      <span class="pb-inspector-search__icon" aria-hidden="true">⌕</span>
      <input
        id="pb-inspector-search-input"
        class="pb-inspector-search__input"
        type="search"
        :value="modelValue"
        placeholder="Search settings…"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="modelValue"
        type="button"
        class="pb-inspector-search__clear"
        aria-label="Clear search"
        @click="emit('update:modelValue', '')"
      >
        ×
      </button>
    </div>
  </div>
</template>
