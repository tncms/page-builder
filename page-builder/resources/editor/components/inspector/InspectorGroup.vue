<script setup lang="ts">
// A labelled, collapsible group of fields inside a tab (schema entry type:'group').
// Collapse state is editor-local (never persisted). A dirty dot + count surface
// changes inside the group; search forces matching groups open (§10/§12).
import { computed, inject, ref } from 'vue'
import InspectorField from './InspectorField.vue'
import { FieldControllerKey, InspectorViewKey } from './fieldController'
import { isGroup, type SchemaGroup } from './schema'

const props = defineProps<{ group: SchemaGroup }>()

const ctrl = inject(FieldControllerKey)
const view = inject(InspectorViewKey)

const collapsed = ref(false)
// While searching, matching groups are forced open so results are visible.
const open = computed(() => view?.searching || !collapsed.value)
const visible = computed(() => view?.entryVisible(props.group) ?? true)
const dirtyCount = computed(() => ctrl?.dirtyCount(props.group.fields) ?? 0)
const label = computed(() => props.group.label || props.group.name)
</script>

<template>
  <fieldset v-if="visible" class="pb-inspector-group" :class="{ 'pb-inspector-group--dirty': dirtyCount > 0 }">
    <legend class="pb-inspector-group__legend">
      <button
        type="button"
        class="pb-inspector-group__toggle"
        :aria-expanded="open"
        @click="collapsed = !collapsed"
      >
        <span class="pb-inspector-group__caret" :class="{ 'is-open': open }" aria-hidden="true">▸</span>
        <span class="pb-inspector-group__label">{{ label }}</span>
        <span
          v-if="dirtyCount > 0"
          class="pb-inspector-group__dirty-dot"
          :title="`${dirtyCount} modified`"
          aria-hidden="true"
        ></span>
      </button>
    </legend>
    <div v-show="open" class="pb-inspector-group__body">
      <template v-for="(entry, i) in group.fields" :key="entry.name || i">
        <InspectorGroup v-if="isGroup(entry)" :group="entry" />
        <InspectorField v-else :field="entry" />
      </template>
    </div>
  </fieldset>
</template>
