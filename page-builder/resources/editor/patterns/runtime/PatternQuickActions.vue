<script setup lang="ts">
// Phase 26.15 — a reusable quick-action bar for a pattern card/row. It only invokes the
// EXISTING flows: insert (picker → insertPattern), rename/duplicate/delete (management →
// dialogs). No new logic, no new pipeline.
import { usePatternPickerStore } from '../picker/patternPickerStore'
import { usePatternManagementStore } from '../management/patternManagementStore'
import type { PatternDefinition } from '../library'

const props = defineProps<{ pattern: PatternDefinition }>()

const picker = usePatternPickerStore()
const management = usePatternManagementStore()
</script>

<template>
  <div class="pb-quick" role="group" :aria-label="`Actions for ${props.pattern.name}`">
    <button type="button" class="pb-quick__btn pb-quick__btn--primary" data-action="insert" :aria-label="`Insert ${props.pattern.name}`" @click="picker.insert(props.pattern.id)">
      Insert
    </button>
    <button type="button" class="pb-quick__btn" data-action="rename" :aria-label="`Rename ${props.pattern.name}`" @click="management.openRename(props.pattern.id)">
      Rename
    </button>
    <button type="button" class="pb-quick__btn" data-action="duplicate" :aria-label="`Duplicate ${props.pattern.name}`" @click="management.duplicate(props.pattern.id)">
      Duplicate
    </button>
    <button type="button" class="pb-quick__btn pb-quick__btn--danger" data-action="delete" :aria-label="`Delete ${props.pattern.name}`" @click="management.openDelete(props.pattern.id)">
      Delete
    </button>
  </div>
</template>

<style scoped>
.pb-quick { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.pb-quick__btn { border: 1px solid #cbd5e1; border-radius: 0.35rem; background: #fff; padding: 0.25rem 0.5rem; font-size: 0.68rem; cursor: pointer; color: #475569; }
.pb-quick__btn:hover { background: #f8fafc; }
.pb-quick__btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.pb-quick__btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-quick__btn--primary:hover { background: #1d4ed8; }
.pb-quick__btn--danger { color: #dc2626; border-color: #fecaca; }
.pb-quick__btn--danger:hover { background: #fef2f2; }
</style>
