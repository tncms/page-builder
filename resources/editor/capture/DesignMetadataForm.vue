<script setup lang="ts">
// Phase 26.48 — the Save as Design metadata form (Rule 3). Name / Description /
// Category / Tags are editable; Version (1.0.0) and Visibility (My Designs) are
// fixed for this phase (Rules 16/18). Two-way bound via defineModel.
const name = defineModel<string>('name', { default: '' })
const description = defineModel<string>('description', { default: '' })
const category = defineModel<string>('category', { default: '' })
const tags = defineModel<string>('tags', { default: '' })
</script>

<template>
  <form class="pb-cap-form" data-testid="design-metadata-form" @submit.prevent>
    <label class="pb-cap-form__field">
      <span>Name</span>
      <input v-model="name" type="text" required data-testid="cap-name" placeholder="My section" />
    </label>
    <label class="pb-cap-form__field">
      <span>Description</span>
      <textarea v-model="description" rows="2" data-testid="cap-description" placeholder="Optional"></textarea>
    </label>
    <label class="pb-cap-form__field">
      <span>Category</span>
      <input v-model="category" type="text" data-testid="cap-category" placeholder="general" />
    </label>
    <label class="pb-cap-form__field">
      <span>Tags</span>
      <input v-model="tags" type="text" data-testid="cap-tags" placeholder="comma, separated" />
    </label>
    <div class="pb-cap-form__meta">
      <span>Version <strong>1.0.0</strong></span>
      <span>Visibility <strong>My Designs</strong></span>
    </div>
  </form>
</template>

<style scoped>
.pb-cap-form { display: flex; flex-direction: column; gap: 0.6rem; }
.pb-cap-form__field { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem; color: var(--pb-editor-text-muted, #9aa4b2); }
.pb-cap-form__field input,
.pb-cap-form__field textarea {
  background: var(--pb-panel, #0f1115); color: var(--pb-editor-text, #e7eaf0); border: 1px solid var(--pb-border, #262b36);
  border-radius: 8px; padding: 0.5rem 0.6rem; font: inherit; resize: vertical;
}
.pb-cap-form__meta { display: flex; gap: 1rem; font-size: 0.72rem; color: var(--pb-editor-text-muted, #9aa4b2); }
.pb-cap-form__meta strong { color: var(--pb-editor-text, #e7eaf0); }
</style>
