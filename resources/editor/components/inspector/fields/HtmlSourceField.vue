<script lang="ts">
// Module scope: a stable per-instance id for the help text (aria-describedby).
let helpSequence = 0
</script>

<script setup lang="ts">
// PB-TEXT-AUTHORING-R1 — Custom HTML source editor (field type `htmlsource`).
//
// Plain source input: the value is stored exactly as typed (per editing locale) and
// is never executed or previewed here. The canvas shows the server-rendered result,
// which runs Core's `cms_html()` and Page Builder's narrowing, so unsafe markup is
// removed there. Over the byte / nesting bounds the value is not staged (the server
// would refuse the save with a 422); the author sees a bounded inline error instead.
import { computed, ref } from 'vue'
import type { SchemaField } from '../schema'
import { byteLength } from '../richText/richTextHtml'

const props = defineProps<{ modelValue: unknown; field: SchemaField }>()
const emit = defineEmits<{ update: [value: string]; commit: []; revert: [] }>()

const strings = computed(() => (props.field.editor?.strings ?? {}) as Record<string, string>)
const t = (key: string, fallback: string): string => strings.value[key] || fallback
const maxBytes = computed(() => Number(props.field.editor?.maxBytes ?? 65536))
const maxDepth = computed(() => Number(props.field.editor?.maxDepth ?? 32))
const value = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : ''))
const invalid = ref(false)
const helpId = `pb-html-help-${++helpSequence}`

const VOID = new Set(['br', 'hr', 'img', 'wbr', 'input', 'meta', 'link', 'source', 'area', 'col', 'embed', 'param', 'track', 'base'])

/** Same cheap open-element scan the server uses (Support\AuthoredHtml::violation). */
function tooDeep(source: string): boolean {
  let depth = 0
  for (const match of source.matchAll(/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)[^>]*?(\/?)>/g)) {
    if (match[1] === '/') depth = Math.max(0, depth - 1)
    else if (match[3] !== '/' && !VOID.has(match[2].toLowerCase()) && ++depth > maxDepth.value) return true
  }
  return false
}

function onInput(event: Event): void {
  const source = (event.target as HTMLTextAreaElement).value
  invalid.value = byteLength(source) > maxBytes.value || tooDeep(source)
  if (!invalid.value) emit('update', source)
}
</script>

<template>
  <div class="pb-htmlsource">
    <textarea
      class="pb-inspector-input pb-inspector-textarea pb-htmlsource__input"
      rows="8"
      spellcheck="false"
      autocapitalize="off"
      autocomplete="off"
      wrap="off"
      :value="value"
      :placeholder="t('htmlEmpty', 'No HTML yet. Type or paste HTML source here.')"
      :aria-label="t('htmlSource', 'HTML source')"
      :aria-describedby="helpId"
      :aria-invalid="invalid ? 'true' : 'false'"
      @input="onInput"
      @blur="emit('commit')"
      @keydown.esc.prevent="emit('revert')"
    />
    <p v-if="invalid" class="pb-richtext__error" role="alert">
      {{ t('tooLarge', 'This content is too large or too deeply nested. Shorten it to save.') }}
    </p>
    <p :id="helpId" class="pb-htmlsource__help">
      {{ t('htmlHelp', 'Custom HTML is cleaned before it is shown: scripts, styles, forms, embeds, event attributes, unsafe links and images from other sites are removed. The canvas shows the cleaned result.') }}
    </p>
  </div>
</template>
