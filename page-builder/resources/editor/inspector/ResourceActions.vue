<script setup lang="ts">
// Phase 26.47F — Inspector actions. Only Cancel + Insert are implemented. Insert
// reuses the existing insertion pipeline (Rule 10) via the parent. A locked
// resource (Phase 26.45) shows a disabled 🔒 Locked control and can never be
// inserted (Rule 9). Favorite / Save / Copy / Marketplace / Updates / Share are
// RESERVED (Rules 16/19) — intentionally not rendered here.
defineProps<{ locked: boolean; requiredCapability?: string | null; inserting?: boolean }>()
defineEmits<{ cancel: []; insert: [] }>()
</script>

<template>
  <div class="pb-ra" data-testid="resource-actions">
    <button type="button" class="pb-ra__btn" data-testid="cancel" @click="$emit('cancel')">Cancel</button>

    <button
      v-if="locked"
      type="button"
      class="pb-ra__btn pb-ra__btn--locked"
      disabled
      data-testid="insert-locked"
      :title="requiredCapability ? `Requires ${requiredCapability}` : 'Locked'"
    >
      🔒 Locked
    </button>
    <button
      v-else
      type="button"
      class="pb-ra__btn pb-ra__btn--primary"
      :disabled="inserting"
      data-testid="insert"
      @click="$emit('insert')"
    >
      {{ inserting ? 'Inserting…' : 'Insert' }}
    </button>
  </div>
</template>

<style scoped>
.pb-ra { display: flex; gap: 0.5rem; justify-content: flex-end; }
.pb-ra__btn {
  border: 1px solid var(--pb-border, #262b36); background: var(--pb-editor-surface, #171a21);
  color: var(--pb-editor-text, #e7eaf0); border-radius: 8px; padding: 0.55rem 1rem; font: inherit; cursor: pointer;
}
.pb-ra__btn--primary { background: var(--pb-accent, #6ea8fe); color: #0b1220; border-color: transparent; font-weight: 600; }
.pb-ra__btn--locked { color: var(--pb-editor-text-muted, #9aa4b2); cursor: default; }
.pb-ra__btn:disabled { opacity: 0.6; cursor: default; }
</style>
