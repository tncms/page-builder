<script setup lang="ts">
// Phase 26.4 — Responsive field editor (foundation).
//
// A `responsive` field wraps a base field (e.g. spacing) that MAY later carry a
// distinct value per breakpoint. This foundation renders the wrapped field's own
// editor — resolved from the shared registry, so no editor is duplicated — bound to
// the single current value. Per-breakpoint editing is a future capability that will
// layer onto this same value path; today it behaves exactly like the base field, so
// the previously-"Unsupported" padding on Hero is editable again.
import { computed } from 'vue'
import type { SchemaField } from '../schema'
import { resolveFieldEditor } from '../fieldEditorRegistry'
import UnsupportedField from './UnsupportedField.vue'

const props = defineProps<{ field: SchemaField; modelValue: unknown }>()
const emit = defineEmits<{ update: [value: unknown]; commit: []; revert: [] }>()

// The wrapped base field descriptor and its resolved editor.
const inner = computed<SchemaField | null>(() => props.field.field ?? null)
const editor = computed(() => (inner.value ? resolveFieldEditor(inner.value.type) : null))
</script>

<template>
  <div class="pb-inspector-responsive">
    <template v-if="inner && editor">
      <component
        :is="editor.component"
        :model-value="modelValue"
        v-bind="editor.needsField ? { field: inner } : {}"
        @update="emit('update', $event)"
        @commit="emit('commit')"
        @revert="emit('revert')"
      />
      <p class="pb-inspector-hint pb-inspector-responsive__note">Applies to all breakpoints.</p>
    </template>
    <UnsupportedField v-else :field="field" />
  </div>
</template>
