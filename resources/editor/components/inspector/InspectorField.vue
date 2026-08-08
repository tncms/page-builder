<script setup lang="ts">
// One labelled field row. Resolves the field type to a leaf component, reads its
// value from the injected controller, and forwards edits back (debounced) and
// blur (immediate commit). Shows required/translatable/help affordances.
import { computed, inject } from 'vue'
import { FieldControllerKey, InspectorViewKey } from './fieldController'
import type { SchemaField } from './schema'
import { resolveFieldEditor } from './fieldEditorRegistry'
import { validateField } from './fieldValidation'
import UnsupportedField from './fields/UnsupportedField.vue'
import ResponsivePropertyField from './responsive/ResponsivePropertyField.vue'
import StyleBoundField from './styleBinding/StyleBoundField.vue'
import { isStyleBound } from './styleBinding/adoption'
// Registers the built-in editors into the shared registry (idempotent side effect).
import './registerBuiltinFieldEditors'

const props = defineProps<{ field: SchemaField }>()

const ctrl = inject(FieldControllerKey)
const view = inject(InspectorViewKey)

// Phase 24.2 — visibility under the editor-local search, and per-field dirty UX.
const visible = computed(() => view?.fieldVisible(props.field) ?? true)
const dirty = computed(() => ctrl?.fieldDirty(props.field) ?? false)
function onRevertField() {
  ctrl?.revertField(props.field)
}

// Resolve the editor for this field type from the shared Property Editor Registry
// (Phase 26.4). No per-type list lives here anymore.
const editor = computed(() => resolveFieldEditor(props.field.type))
const comp = computed(() => editor.value?.component ?? UnsupportedField)
const supported = computed(() => editor.value !== null)
const needsField = computed(() => editor.value?.needsField ?? false)
// Phase 26.6 — the `responsive` flag turns ANY editor into a per-breakpoint control
// via the shared wrapper. The legacy `type: 'responsive'` wrapper field keeps its own
// editor, so it is excluded here.
const isResponsive = computed(() => props.field.responsive === true && props.field.type !== 'responsive')
// Phase 26.9 — adopted types (color/spacing/typography) get token binding + a source
// indicator via the shared wrapper. Responsive adopted fields are wrapped inside the
// responsive editor instead (per breakpoint), so they are excluded here.
const styleBound = computed(() => isStyleBound(props.field) && !isResponsive.value)
const value = computed(() => ctrl?.getValue(props.field))
const label = computed(() => props.field.label || props.field.name)

// Inline validation feedback (superset of the old required-only check).
const validationError = computed(() => validateField(props.field, value.value))

function onUpdate(v: unknown) {
  ctrl?.setValue(props.field, v)
}
function onCommit() {
  ctrl?.commit()
}
function onRevert() {
  ctrl?.revert(props.field)
}
</script>

<template>
  <div
    v-if="visible"
    class="pb-inspector-field"
    :class="{ 'pb-inspector-field--dirty': dirty }"
    :data-field="field.name"
  >
    <div class="pb-inspector-field__head">
      <label class="pb-inspector-field__label">
        <span v-if="dirty" class="pb-inspector-field__dirty-dot" aria-hidden="true"></span>
        {{ label }}
        <span v-if="field.required" class="pb-inspector-field__req" title="Required">*</span>
        <span v-if="dirty" class="pb-inspector-field__modified-label">Modified</span>
      </label>
      <span v-if="field.translatable" class="pb-inspector-field__badge">Translatable · {{ ctrl?.locale }}</span>
      <button
        v-if="dirty"
        type="button"
        class="pb-inspector-field__revert"
        :aria-label="`Revert ${label} to its applied value`"
        title="Revert to applied value"
        @click="onRevertField"
      >
        ↺
      </button>
    </div>

    <UnsupportedField v-if="!supported" :field="field" />
    <ResponsivePropertyField
      v-else-if="isResponsive"
      :field="field"
      :model-value="value"
      @update="onUpdate"
      @commit="onCommit"
      @revert="onRevert"
    />
    <StyleBoundField
      v-else-if="styleBound"
      :field="field"
      :model-value="value"
      @update="onUpdate"
      @commit="onCommit"
      @revert="onRevert"
    />
    <component
      v-else
      :is="comp"
      :model-value="value"
      v-bind="needsField ? { field } : {}"
      @update="onUpdate"
      @commit="onCommit"
      @revert="onRevert"
    />

    <p v-if="validationError" class="pb-inspector-warn" role="alert">{{ validationError }}</p>
    <p v-else-if="field.help" class="pb-inspector-hint">{{ field.help }}</p>
  </div>
</template>
