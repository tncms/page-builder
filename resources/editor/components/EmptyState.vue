<script setup lang="ts">
defineProps<{ title: string; text?: string }>()
</script>

<template>
  <div class="pb-editor-empty">
    <div class="pb-editor-empty__title">{{ title }}</div>
    <p v-if="text" class="pb-editor-empty__text">{{ text }}</p>
    <slot />
  </div>
</template>
