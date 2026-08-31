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
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useEditorStore } from '../../../editor/stores/editor'
import type { TranslationLocaleOption } from '../localeSwitch'
import { decideLocaleSwitch, saveAndSwitch, switchWithoutSaving } from '../localeSwitch'
import BuilderLocaleSwitchDialog from './BuilderLocaleSwitchDialog.vue'

const store = useEditorStore()

const menuOpen = ref(false)
const pending = ref<TranslationLocaleOption | null>(null)
const rootEl = ref<HTMLElement | null>(null)

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

function select(target: TranslationLocaleOption): void {
  menuOpen.value = false

  const decision = decideLocaleSwitch(target, currentLocale.value, isDirty.value)
  if (decision === 'noop' || !target.editor_url) {
    return
  }

  if (decision === 'navigate') {
    navigate(target.editor_url)
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
  if (target?.editor_url) {
    switchWithoutSaving(target.editor_url, navigate)
  }
}

async function onSave(): Promise<void> {
  const target = pending.value
  if (!target?.editor_url) {
    return
  }

  const navigated = await saveAndSwitch(target.editor_url, {
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
      type="button"
      class="pb-ui-btn pb-live-builder-btn pb-live-builder-btn--ghost pb-live-builder-langswitch__toggle"
      aria-haspopup="listbox"
      :aria-expanded="menuOpen"
      aria-label="Editing language"
      :title="`Editing language: ${currentLabel}`"
      @click="menuOpen = !menuOpen"
    >
      <span aria-hidden="true">🌐</span>
      <span class="pb-live-builder-langswitch__current">{{ currentLabel }}</span>
      <span aria-hidden="true">▾</span>
    </button>

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
          :disabled="option.is_current"
          @click="select(option)"
        >
          <span class="pb-live-builder-langswitch__label">{{ option.label }}</span>
          <span class="pb-live-builder-langswitch__code">{{ option.locale }}</span>
          <span v-if="option.is_source" class="pb-live-builder-langswitch__tag">source</span>
          <span
            class="pb-live-builder-langswitch__dot"
            :class="option.has_layout ? 'is-ready' : 'is-empty'"
            :title="option.has_layout ? 'Has a layout' : 'No layout yet'"
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
