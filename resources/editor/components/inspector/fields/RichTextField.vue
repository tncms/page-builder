<script setup lang="ts">
// PB-TEXT-AUTHORING-R1 — the visual Text editor (field type `richtext`).
//
// A dependency-free contenteditable with a small formatting toolbar. It edits the
// Text block's translatable `content` key for the current editing locale only.
// Opening it never writes: when the locale has no rich value yet it DISPLAYS the
// legacy plain `text` (field `editor.legacyField`) as paragraphs, and only a genuine
// edit emits normalized HTML. Pasted HTML is normalized in an inert document; the
// server sanitizes every render (Core `cms_html()` + Page Builder narrowing), so
// nothing here is a security boundary. Commits on blur like the textarea it replaces.
import { computed, inject, nextTick, onMounted, ref, watch } from 'vue'
import { FieldControllerKey } from '../fieldController'
import type { SchemaField } from '../schema'
import { byteLength, isSafeLinkUrl, normalizeRichHtml, plainToRichHtml } from '../richText/richTextHtml'

const props = defineProps<{ modelValue: unknown; field: SchemaField }>()
const emit = defineEmits<{ update: [value: string]; commit: [] }>()
const ctrl = inject(FieldControllerKey, null)

const strings = computed(() => (props.field.editor?.strings ?? {}) as Record<string, string>)
const t = (key: string, fallback: string): string => strings.value[key] || fallback
const maxBytes = computed(() => Number(props.field.editor?.maxBytes ?? 65536))

const surface = ref<HTMLDivElement | null>(null)
const tooLarge = ref(false)
const linkOpen = ref(false)
const linkUrl = ref('')
const linkNewTab = ref(false)
const linkError = ref(false)
const active = ref<Record<string, boolean>>({})
let lastEmitted: string | null = null
let pendingCommit = false
let savedRange: Range | null = null

/** Whether the current locale has its own rich value (an explicit '' counts). */
function hasOwnRichValue(): boolean {
  const raw = ctrl?.getValue({ ...props.field, translatable: false }) as unknown
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return Object.prototype.hasOwnProperty.call(raw, ctrl?.locale ?? '')
  }
  return typeof props.modelValue === 'string' && props.modelValue !== ''
}

function initialHtml(): string {
  if (hasOwnRichValue()) return typeof props.modelValue === 'string' ? props.modelValue : ''
  const legacyName = props.field.editor?.legacyField
  if (typeof legacyName !== 'string' || !ctrl) return ''
  const legacy = ctrl.getValue({ name: legacyName, type: 'textarea', translatable: true } as SchemaField)
  return typeof legacy === 'string' ? plainToRichHtml(legacy) : ''
}

function load(): void {
  if (!surface.value) return
  surface.value.innerHTML = normalizeRichHtml(initialHtml())
  tooLarge.value = false
}

onMounted(load)
// An outside change (revert, locale switch, selecting another node) reloads the surface;
// our own emitted value echoing back does not (it would reset the caret).
watch(
  () => [props.modelValue, ctrl?.locale],
  () => {
    if (props.modelValue !== lastEmitted) {
      lastEmitted = null
      void nextTick(load)
    }
  },
)

function onInput(): void {
  if (!surface.value) return
  const html = normalizeRichHtml(surface.value.innerHTML)
  tooLarge.value = byteLength(html) > maxBytes.value
  if (tooLarge.value || html === lastEmitted) return
  lastEmitted = html
  pendingCommit = true
  emit('update', html)
}

function onFocusOut(event: FocusEvent): void {
  const next = event.relatedTarget as Node | null
  const root = (event.currentTarget as HTMLElement | null) ?? null
  if (next && root?.contains(next)) return
  if (pendingCommit) {
    pendingCommit = false
    emit('commit')
  }
}

function exec(command: string, value?: string): void {
  surface.value?.focus()
  if (typeof document.execCommand === 'function') document.execCommand(command, false, value)
  onInput()
  refreshState()
}

function escapeText(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function selectionText(): string {
  return window.getSelection()?.toString() ?? ''
}

function inlineCode(): void {
  const text = selectionText()
  if (text !== '') exec('insertHTML', `<code>${escapeText(text)}</code>`)
}

function clearFormatting(): void {
  exec('removeFormat')
  exec('unlink')
  exec('formatBlock', 'p')
}

function onPaste(event: ClipboardEvent): void {
  event.preventDefault()
  const html = event.clipboardData?.getData('text/html') ?? ''
  const text = event.clipboardData?.getData('text/plain') ?? ''
  const safe = html !== '' ? normalizeRichHtml(html) : plainToRichHtml(text)
  if (safe !== '') exec('insertHTML', safe)
}

function rememberSelection(): void {
  const selection = window.getSelection()
  savedRange = selection && selection.rangeCount > 0 && surface.value?.contains(selection.anchorNode)
    ? selection.getRangeAt(0).cloneRange()
    : null
}

function restoreSelection(): void {
  if (!savedRange) return
  const selection = window.getSelection()
  selection?.removeAllRanges()
  selection?.addRange(savedRange)
}

function openLink(): void {
  rememberSelection()
  linkUrl.value = ''
  linkNewTab.value = false
  linkError.value = false
  linkOpen.value = true
}

function applyLink(): void {
  const url = linkUrl.value.trim()
  if (!isSafeLinkUrl(url)) {
    linkError.value = true
    return
  }
  surface.value?.focus()
  restoreSelection()
  const selection = window.getSelection()
  if (selection && !selection.isCollapsed && selectionText() !== '') {
    // Wrap the selection itself so its formatting (bold, italic …) is kept.
    const before = new Set(surface.value?.querySelectorAll('a') ?? [])
    if (typeof document.execCommand === 'function') document.execCommand('createLink', false, url)
    markNewTab(url, selection, before)
    onInput()
    refreshState()
  } else {
    const target = linkNewTab.value ? ' target="_blank" rel="noopener noreferrer"' : ''
    exec('insertHTML', `<a href="${escapeText(url)}"${target}>${escapeText(url)}</a>`)
  }
  linkOpen.value = false
}

/** Apply the "open in a new tab" choice to the anchors the link command just created. */
function markNewTab(url: string, selection: Selection, before: Set<HTMLAnchorElement>): void {
  surface.value?.querySelectorAll('a').forEach((anchor) => {
    if (anchor.getAttribute('href') !== url) return
    // A new anchor, or an existing one the command re-pointed inside the selection.
    const created = !before.has(anchor)
    if (!created && !(typeof selection.containsNode === 'function' && selection.containsNode(anchor, true))) return
    if (linkNewTab.value) {
      anchor.setAttribute('target', '_blank')
      anchor.setAttribute('rel', 'noopener noreferrer')
    } else {
      anchor.removeAttribute('target')
      anchor.removeAttribute('rel')
    }
  })
}

function removeLink(): void {
  surface.value?.focus()
  restoreSelection()
  exec('unlink')
  linkOpen.value = false
}

function refreshState(): void {
  if (typeof document.queryCommandState !== 'function') return
  const state: Record<string, boolean> = {}
  for (const command of ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList']) {
    try {
      state[command] = document.queryCommandState(command)
    } catch {
      state[command] = false
    }
  }
  active.value = state
}

interface ToolButton {
  key: string
  label: string
  glyph: string
  run: () => void
  state?: string
}

const buttons = computed<ToolButton[]>(() => [
  { key: 'bold', label: t('bold', 'Bold'), glyph: 'B', run: () => exec('bold'), state: 'bold' },
  { key: 'italic', label: t('italic', 'Italic'), glyph: 'I', run: () => exec('italic'), state: 'italic' },
  { key: 'underline', label: t('underline', 'Underline'), glyph: 'U', run: () => exec('underline'), state: 'underline' },
  { key: 'bulleted', label: t('bulleted', 'Bulleted list'), glyph: '•', run: () => exec('insertUnorderedList'), state: 'insertUnorderedList' },
  { key: 'numbered', label: t('numbered', 'Numbered list'), glyph: '1.', run: () => exec('insertOrderedList'), state: 'insertOrderedList' },
  { key: 'quote', label: t('quote', 'Quote'), glyph: '❝', run: () => exec('formatBlock', 'blockquote') },
  { key: 'code', label: t('code', 'Inline code'), glyph: '</>', run: inlineCode },
  { key: 'codeBlock', label: t('codeBlock', 'Code block'), glyph: '{ }', run: () => exec('formatBlock', 'pre') },
  { key: 'link', label: t('link', 'Link'), glyph: '🔗', run: openLink },
  { key: 'clear', label: t('clear', 'Remove formatting'), glyph: 'T̸', run: clearFormatting },
  { key: 'undo', label: t('undo', 'Undo'), glyph: '↶', run: () => exec('undo') },
  { key: 'redo', label: t('redo', 'Redo'), glyph: '↷', run: () => exec('redo') },
])
</script>

<template>
  <div class="pb-richtext" @focusout="onFocusOut">
    <div class="pb-richtext__toolbar" role="toolbar" :aria-label="t('toolbar', 'Text formatting')">
      <button
        v-for="button in buttons"
        :key="button.key"
        type="button"
        class="pb-richtext__tool"
        :class="{ 'is-active': button.state && active[button.state] }"
        :data-tool="button.key"
        :aria-label="button.label"
        :title="button.label"
        :aria-pressed="button.state ? String(!!active[button.state]) : undefined"
        @mousedown.prevent
        @click="button.run()"
      >
        <span aria-hidden="true">{{ button.glyph }}</span>
      </button>
    </div>

    <div v-if="linkOpen" class="pb-richtext__link" role="group" :aria-label="t('link', 'Link')">
      <label class="pb-richtext__link-field">
        <span>{{ t('linkUrl', 'Link address') }}</span>
        <input
          v-model="linkUrl"
          type="text"
          inputmode="url"
          class="pb-inspector-input"
          data-link-url
          :aria-invalid="linkError ? 'true' : 'false'"
          @keydown.enter.prevent="applyLink"
          @keydown.esc.prevent="linkOpen = false"
        />
      </label>
      <label class="pb-richtext__link-tab">
        <input v-model="linkNewTab" type="checkbox" />
        <span>{{ t('linkNewTab', 'Open in a new tab') }}</span>
      </label>
      <p v-if="linkError" class="pb-richtext__error" role="alert">
        {{ t('linkInvalid', 'Use an address that starts with https://, http://, mailto:, tel:, / or #.') }}
      </p>
      <div class="pb-richtext__link-actions">
        <button type="button" class="pb-richtext__tool" data-link-apply @click="applyLink">{{ t('linkApply', 'Apply link') }}</button>
        <button type="button" class="pb-richtext__tool" data-link-remove @click="removeLink">{{ t('linkRemove', 'Remove link') }}</button>
      </div>
    </div>

    <div
      ref="surface"
      class="pb-inspector-input pb-richtext__surface"
      contenteditable="true"
      role="textbox"
      aria-multiline="true"
      :aria-label="t('editor', 'Text editor')"
      :aria-invalid="tooLarge ? 'true' : 'false'"
      @input="onInput"
      @paste="onPaste"
      @keyup="refreshState"
      @mouseup="refreshState"
      @keydown.esc.prevent="surface?.blur()"
    />
    <p v-if="tooLarge" class="pb-richtext__error" role="alert">
      {{ t('tooLarge', 'This content is too large or too deeply nested. Shorten it to save.') }}
    </p>
  </div>
</template>
