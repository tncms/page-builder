<script setup lang="ts">
// LT-M1B Increment 2B — the in-editor language switcher (Live Builder top bar).
//
// Consumes the SERVER-prepared model (store.availableLocales) — it never derives
// owner/layout/translation identity; each option carries its own server-generated
// locale-aware `editor_url`. Switching is a full editor navigation to that URL.
// Unsaved changes are guarded through the editor's existing dirty-state authority
// (store.dirty || store.inspectorDirty) and the shared draft-save pipeline
// (store.saveDraft), via the accessible unsaved-change dialog — never a raw
// confirm(). The whole control hides unless there is more than one locale to
// switch between (single-language / feature-off => empty model).
//
// A locale with no Core content translation is never opened by URL (that fails
// closed server-side). When the server marks it creatable, selecting it POSTs the
// server `create_url`, which creates the translation + layout atomically, and then
// opens the editor URL the server returns. Otherwise it is listed but disabled.
//
// PB-CORE-AUTHORITY-REMEDIATION-R1 — when the source language has no Page Builder
// content to copy, the server refuses that create with the blank-layout decision
// (nothing written); the switcher then asks the user explicitly: Create blank
// translation / Return to content editor / Cancel.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import type { BlankLayoutDecision, TranslationLocaleOption } from '../localeSwitch'
import {
  blankDecisionFrom,
  createBlankAndOpen,
  decideLocaleSwitch,
  isOpenable,
  openLocaleTarget,
  saveAndSwitch,
} from '../localeSwitch'
import BuilderBlankTranslationDialog from './BuilderBlankTranslationDialog.vue'
import BuilderLocaleSwitchDialog from './BuilderLocaleSwitchDialog.vue'

const store = useEditorStore()

const menuOpen = ref(false)
const pending = ref<TranslationLocaleOption | null>(null)
const rootEl = ref<HTMLElement | null>(null)
const toggleEl = ref<HTMLButtonElement | null>(null)
const opening = ref(false)
const openError = ref<string | null>(null)
const blankDecision = ref<BlankLayoutDecision | null>(null)
const blankBusy = ref(false)
const blankError = ref<string | null>(null)
const t = (key: string, fallback: string): string => store.uiString(key, fallback)

const locales = computed<TranslationLocaleOption[]>(() => store.availableLocales)
const enabled = computed(() => locales.value.length > 1)
const currentLocale = computed(() => store.currentLocale)
// PB-PROD-2 — any unsaved risk (dirty, armed/in-flight autosave, or a failed save)
// gates a locale switch, not just committed dirtiness.
const isDirty = computed(() => store.hasUnsavedRisk)

const currentOption = computed<TranslationLocaleOption | null>(
  () => locales.value.find((l) => l.is_current) ?? locales.value.find((l) => l.locale === currentLocale.value) ?? null,
)
const currentLabel = computed(() => currentOption.value?.label ?? currentLocale.value.toUpperCase())

// The group's source (default) locale, shown beneath the toggle so the editor
// always states what it is editing and — for a translation — what it translates
// from. Derived from the SAME server projection; never computed client-side.
const sourceOption = computed<TranslationLocaleOption | null>(() => locales.value.find((l) => l.is_source) ?? null)
const sourceLabel = computed(() => sourceOption.value?.label ?? '')
const isCurrentSource = computed(
  () => currentOption.value?.is_source ?? sourceOption.value?.locale === currentLocale.value,
)

function navigate(url: string): void {
  if (typeof window !== 'undefined') {
    window.location.assign(url)
  }
}

async function createTranslation(createUrl: string, locale: string, layout?: 'blank'): Promise<string | null> {
  if (!store.client) {
    throw new Error('The editor is not ready yet. Please try again.')
  }

  const created = layout
    ? await store.client.createTranslation(createUrl, locale, layout)
    : await store.client.createTranslation(createUrl, locale)

  return created.editor_url
}

/** Open (or create, then open) the target locale; surfaces a failure inline and stays put. */
async function openTarget(target: TranslationLocaleOption): Promise<void> {
  openError.value = null
  opening.value = true

  try {
    await openLocaleTarget(target, { create: createTranslation, navigate })
  } catch (error) {
    // No source content to copy: ask explicitly instead of failing.
    const decision = target.create_url ? blankDecisionFrom(error, target.create_url) : null
    if (decision) {
      blankError.value = null
      blankDecision.value = decision
      return
    }
    openError.value = error instanceof Error ? error.message : 'This language could not be opened.'
  } finally {
    opening.value = false
  }
}

async function onBlankCreate(): Promise<void> {
  const decision = blankDecision.value
  if (!decision || blankBusy.value) {
    return
  }

  blankError.value = null
  blankBusy.value = true
  try {
    await createBlankAndOpen(decision, { create: createTranslation, navigate })
  } catch (error) {
    blankError.value = error instanceof Error ? error.message : t('blankDecisionFailed', 'The blank translation could not be created.')
  } finally {
    blankBusy.value = false
  }
}

function onBlankReturn(): void {
  const url = blankDecision.value?.returnUrl
  if (url) {
    navigate(url)
  }
}

/** Cancel writes nothing and keeps the current editor exactly as it is; focus returns to the toggle. */
function onBlankCancel(): void {
  blankDecision.value = null
  blankError.value = null
  void nextTick(() => toggleEl.value?.focus())
}

function optionHint(option: TranslationLocaleOption): string {
  if (option.has_translation) {
    return option.has_layout ? 'Has a layout' : 'No layout yet'
  }

  return option.can_create ? `Create the ${option.label} translation` : 'Not translated yet'
}

function select(target: TranslationLocaleOption): void {
  menuOpen.value = false

  const decision = decideLocaleSwitch(target, currentLocale.value, isDirty.value)
  if (decision === 'noop' || opening.value) {
    return
  }

  if (decision === 'navigate') {
    void openTarget(target)
    return
  }

  // Dirty — resolve through the unsaved-change dialog.
  pending.value = target
}

function onStay(): void {
  pending.value = null
}

function onDiscard(): void {
  const target = pending.value
  pending.value = null
  // A full navigation drops the unsaved client state without saving it.
  if (target && isOpenable(target)) {
    void openTarget(target)
  }
}

async function onSave(): Promise<void> {
  const target = pending.value
  if (!target || !isOpenable(target)) {
    return
  }

  const navigated = await saveAndSwitch(() => openTarget(target), {
    save: () => store.saveDraft(),
    stillDirty: () => store.hasUnsavedRisk,
    navigate,
  })

  // A failed save keeps the editor dirty; stay in the dialog so the existing
  // typed save error (store.error) is shown and the user can retry.
  if (navigated) {
    pending.value = null
  }
}

function onDocumentClick(event: MouseEvent): void {
  if (rootEl.value && !rootEl.value.contains(event.target as Node)) {
    menuOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick, true))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick, true))
</script>

<template>
  <div v-if="enabled" ref="rootEl" class="pb-live-builder-langswitch">
    <span class="pb-live-builder-langswitch__heading">Editing language</span>

    <button
      ref="toggleEl"
      type="button"
      class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost pb-live-builder-langswitch__toggle"
      aria-haspopup="listbox"
      :aria-expanded="menuOpen"
      aria-label="Editing language"
      :title="`Editing language: ${currentLabel}`"
      :disabled="opening"
      :aria-busy="opening"
      @click="menuOpen = !menuOpen"
    >
      <span aria-hidden="true">🌐</span>
      <span class="pb-live-builder-langswitch__current">{{ currentLabel }}</span>
      <span aria-hidden="true">▾</span>
    </button>

    <p v-if="opening" class="pb-live-builder-langswitch__status" role="status">Opening language…</p>
    <p v-if="openError" class="pb-live-builder-langswitch__error" role="alert">{{ openError }}</p>

    <p v-if="sourceLabel" class="pb-live-builder-langswitch__source">
      <template v-if="isCurrentSource">Source language: {{ sourceLabel }}</template>
      <template v-else>Translating from: {{ sourceLabel }}</template>
    </p>

    <ul v-if="menuOpen" class="pb-live-builder-langswitch__menu" role="listbox" aria-label="Available languages">
      <li v-for="option in locales" :key="option.locale" role="none">
        <button
          type="button"
          role="option"
          class="pb-live-builder-langswitch__option"
          :class="{ 'is-current': option.is_current }"
          :aria-selected="option.is_current"
          :disabled="option.is_current || !isOpenable(option)"
          :title="optionHint(option)"
          @click="select(option)"
        >
          <span class="pb-live-builder-langswitch__label">{{ option.label }}</span>
          <span class="pb-live-builder-langswitch__code">{{ option.locale }}</span>
          <span v-if="option.is_source" class="pb-live-builder-langswitch__tag">source</span>
          <span v-else-if="!option.has_translation" class="pb-live-builder-langswitch__tag is-new">
            {{ option.can_create ? 'create' : 'not translated' }}
          </span>
          <span
            class="pb-live-builder-langswitch__dot"
            :class="option.has_layout ? 'is-ready' : 'is-empty'"
            aria-hidden="true"
          ></span>
        </button>
      </li>
    </ul>

    <BuilderLocaleSwitchDialog
      v-if="pending"
      :target-label="pending.label"
      :saving="store.saving"
      :error="store.error"
      @stay="onStay"
      @discard="onDiscard"
      @save="onSave"
    />

    <BuilderBlankTranslationDialog
      v-if="blankDecision"
      :can-return="Boolean(blankDecision.returnUrl)"
      :busy="blankBusy"
      :error="blankError"
      :t="t"
      @create="onBlankCreate"
      @return="onBlankReturn"
      @cancel="onBlankCancel"
    />
  </div>
</template>

<style scoped>
.pb-live-builder-langswitch {
  position: relative;
  display: block;
}

.pb-live-builder-langswitch__heading {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}

.pb-live-builder-langswitch__toggle {
  display: inline-flex;
  align-items: center;
  width: 100%;
  justify-content: flex-start;
}

.pb-live-builder-langswitch__current {
  margin: 0 0.35rem;
  font-weight: 600;
}

.pb-live-builder-langswitch__source {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: #64748b;
}

.pb-live-builder-langswitch__menu {
  position: absolute;
  top: calc(100% + 0.35rem);
  left: 0;
  right: auto;
  z-index: 900;
  min-width: 12rem;
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
}

.pb-live-builder-langswitch__option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.4rem 0.5rem;
  border: 0;
  border-radius: 0.375rem;
  background: transparent;
  color: #0f172a;
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
}

.pb-live-builder-langswitch__option:hover:not(:disabled) {
  background: #f1f5f9;
}

.pb-live-builder-langswitch__option.is-current {
  font-weight: 600;
  cursor: default;
}

.pb-live-builder-langswitch__code {
  margin-left: auto;
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #64748b;
}

.pb-live-builder-langswitch__tag {
  font-size: 0.65rem;
  text-transform: uppercase;
  color: #2563eb;
}

.pb-live-builder-langswitch__tag.is-new {
  color: #b45309;
}

.pb-live-builder-langswitch__option:disabled:not(.is-current) {
  color: #94a3b8;
  cursor: not-allowed;
}

.pb-live-builder-langswitch__status,
.pb-live-builder-langswitch__error {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
}

.pb-live-builder-langswitch__status {
  color: #64748b;
}

.pb-live-builder-langswitch__error {
  color: #b91c1c;
}

.pb-live-builder-langswitch__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
}

.pb-live-builder-langswitch__dot.is-ready {
  background: #16a34a;
}

.pb-live-builder-langswitch__dot.is-empty {
  background: #cbd5e1;
}
</style>
