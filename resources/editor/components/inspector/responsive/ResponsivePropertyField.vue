<script setup lang="ts">
// Phase 26.6 — Responsive property wrapper.
//
// A SHARED capability, not a per-type component: it wraps ANY registered editor when
// a field declares `responsive: true`. It resolves the inner editor from the same
// Property Editor Registry (no duplication), shows a breakpoint tab strip, binds the
// inner editor to the *effective* value for the active breakpoint, and translates the
// inner editor's edits into a `{ desktop, tablet, mobile }` envelope.
//
// The whole envelope stays one value under the field's `name`, so the draft/diff/undo
// path is unchanged: one Apply = one `update-component-data` = one undo entry.
import { computed } from 'vue'
import type { SchemaField } from '../schema'
import { resolveFieldEditor } from '../fieldEditorRegistry'
import UnsupportedField from '../fields/UnsupportedField.vue'
import StyleBoundField from '../styleBinding/StyleBoundField.vue'
import { isStyleBound } from '../styleBinding/adoption'
import { listBreakpoints, DEFAULT_BREAKPOINT } from './breakpoints'
import { activeBreakpoint, setActiveBreakpoint } from './activeBreakpoint'
import { resolveEffective, setBreakpoint, clearBreakpoint, hasOwnOverride } from './responsiveValue'

const props = defineProps<{ field: SchemaField; modelValue: unknown }>()
const emit = defineEmits<{ update: [value: unknown]; commit: []; revert: [] }>()

// The inner editor is resolved by the field's real type (spacing/color/…), from the
// shared registry — exactly what a non-responsive field would use. For an adopted type
// (Phase 26.9) the inner is the StyleBoundField wrapper, so a token can be bound per
// breakpoint; it resolves the real editor itself.
const descriptor = computed(() => resolveFieldEditor(props.field.type))
const styleBound = computed(() => isStyleBound(props.field))
const inner = computed(() => (styleBound.value ? StyleBoundField : descriptor.value?.component ?? UnsupportedField))
// StyleBoundField always needs `field`; a raw editor only when its descriptor says so.
const needsField = computed(() => styleBound.value || (descriptor.value?.needsField ?? false))

const breakpoints = listBreakpoints()
const device = computed(() => activeBreakpoint.value)

// What the inner editor sees for the active breakpoint (cascaded).
const effective = computed(() => resolveEffective(props.modelValue, device.value))

const overriding = computed(() => device.value !== DEFAULT_BREAKPOINT && hasOwnOverride(props.modelValue, device.value))

function onInner(next: unknown): void {
  emit('update', setBreakpoint(props.modelValue, device.value, next))
}
function onReset(): void {
  emit('update', clearBreakpoint(props.modelValue, device.value))
  emit('commit')
}
</script>

<template>
  <div class="pb-inspector-responsive-field">
    <div class="pb-inspector-responsive-field__bar">
      <div class="pb-inspector-responsive-field__tabs" role="tablist" aria-label="Breakpoint">
        <button
          v-for="bp in breakpoints"
          :key="bp.id"
          type="button"
          role="tab"
          class="pb-inspector-responsive-field__tab"
          :class="{ 'is-active': bp.id === device }"
          :aria-selected="bp.id === device"
          :title="bp.label"
          @click="setActiveBreakpoint(bp.id)"
        >
          <span class="pb-inspector-responsive-field__icon" aria-hidden="true">{{ bp.icon }}</span>
          <span
            v-if="hasOwnOverride(modelValue, bp.id)"
            class="pb-inspector-responsive-field__dot"
            :title="`${bp.label} override`"
            aria-hidden="true"
          ></span>
        </button>
      </div>
      <button
        v-if="overriding"
        type="button"
        class="pb-inspector-iconbtn"
        title="Clear this breakpoint (inherit from Desktop)"
        aria-label="Clear this breakpoint override"
        @click="onReset"
      >
        ↺
      </button>
    </div>

    <component
      :is="inner"
      :model-value="effective"
      v-bind="needsField ? { field } : {}"
      @update="onInner"
      @commit="emit('commit')"
      @revert="emit('revert')"
    />
  </div>
</template>
