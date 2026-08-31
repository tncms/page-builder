<script setup lang="ts">
// Phase 24.1/24.2 — Inspector Apply Workflow + UX polish.
//
// Three editing layers (Elementor/Bricks/Webflow style):
//   L1 Live Preview — a keystroke mirrors into the iframe via the Phase 17 Live
//      Patch engine. No command, no BuilderState, no DB.
//   L2 Component Draft — edits accumulate in `draftData`; the component goes dirty.
//   L3 Apply — Apply / Ctrl+Enter / auto-flush commits ONE update-component-data.
//
// Phase 24.2 adds: distinct Cancel vs Reset-to-defaults, per-field/tab/group dirty
// UX, a dirty counter, a sticky action footer, Apply state feedback, per-field
// revert, an editor-local field search, collapsible groups, validation gating and
// accessibility. Nothing is hardcoded per component type — fields come from schema.
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { useEditorStore } from '../../stores/editor'
import { clonePlain, getByPath, setByPath } from '../../utils/dataPath'
import {
  FieldControllerKey,
  InspectorViewKey,
  type FieldController,
  type InspectorView,
} from './fieldController'
import { isGroup, type ComponentSchema, type SchemaEntry, type SchemaField } from './schema'
import { validateField } from './fieldValidation'
import { resolveEffective } from './responsive/responsiveValue'
import { activeBreakpoint } from './responsive/activeBreakpoint'
import { useTokens } from './designTokens/useTokens'
import { StyleContextKey } from './styleBinding/styleContext'
import { resolveComponentStyleValue } from './styleBinding/resolveComponentStyleValue'
import { buildDefaultData, diffFieldPaths, editableFields, hasMeaningfulValues } from './resetData'
import InspectorTabs from './InspectorTabs.vue'
import InspectorSearch from './InspectorSearch.vue'
import UnknownNodePlaceholder from '../UnknownNodePlaceholder.vue'

const store = useEditorStore()
const rootEl = ref<HTMLElement | null>(null)
// Live token registry (Phase 26.7) — used to resolve token references for live preview
// and provided to adopted editors via the style context (Phase 26.9).
const tokensRef = useTokens()

// L2 staging.
const draftData = ref<Record<string, unknown>>({})
const appliedData = ref<Record<string, unknown>>({})
const dirtyFields = ref<Set<string>>(new Set())
const activeId = ref<string | null>(null)
// The explicitly-applied preset name (reset priority §2). Cleared on cancel/switch.
const activePreset = ref<string | null>(null)

// Apply lifecycle: idle → applying → (applied | error); applied auto-clears.
type ApplyState = 'idle' | 'applying' | 'applied' | 'error'
const applyState = ref<ApplyState>('idle')
let appliedTimer: number | null = null

// Editor-local field search.
const search = ref('')

// Validation: which required fields are empty, and whether to surface the error.
const validationVisible = ref(false)
const focusField = ref<string | null>(null)

// Dialogs.
const switchDialog = ref(false)
const pendingSwitchId = ref<string | null>(null)
const resetConfirm = ref(false)
let selectionGuard = false

// Phase 26.3, Increment 4.2 — command dirty-guard. When a structural command
// (move/delete/duplicate/cut/paste) fires while a draft is staged, the store
// awaits us; we open the SAME switch dialog and park this resolver until the
// user picks Apply / Discard / Stay. Distinct from a selection switch, so
// `pendingSwitchId` stays null and the existing branch is bypassed.
let commandGuardResolve: ((proceed: boolean) => void) | null = null

const definition = computed(() => store.selectedDefinition)
const selected = computed(() => store.selectedComponent)
const locale = computed(() => store.currentLocale)
const schema = computed(() => definition.value?.schema as ComponentSchema | undefined)

const tabs = computed(() => (Array.isArray(schema.value?.tabs) ? schema.value!.tabs : []))
const hasFields = computed(() => tabs.value.some((t) => (t.fields?.length ?? 0) > 0))

const label = computed(() => {
  const def = definition.value
  return (def?.label as string) || (def?.name as string) || selected.value?.type || 'Component'
})

const fieldMap = computed(() => editableFields(schema.value))

const isDirty = computed(() => dirtyFields.value.size > 0)
const dirtyCount = computed(() => dirtyFields.value.size)

// ---- Presets (staged, never committed) --------------------------------
interface ComponentPreset {
  name: string
  label: string
  data: Record<string, unknown>
}
const presets = computed<ComponentPreset[]>(() => {
  const editor = (definition.value?.editor ?? {}) as { presets?: ComponentPreset[] }
  return Array.isArray(editor.presets) ? editor.presets : []
})
const activePresetData = computed<Record<string, unknown> | undefined>(() =>
  activePreset.value ? presets.value.find((p) => p.name === activePreset.value)?.data : undefined,
)

function applyPreset(preset: ComponentPreset): void {
  if (!activeId.value) return
  activePreset.value = preset.name
  const next = { ...clonePlain(draftData.value), ...clonePlain(preset.data) }
  draftData.value = next
  for (const key of Object.keys(preset.data)) {
    dirtyFields.value.add(key)
    emitFieldPatch(key)
  }
  dirtyFields.value = new Set(dirtyFields.value)
  onEdited()
}

// ---- Validation --------------------------------------------------------
// Phase 26.4 — gate Apply on the shared validator (required + type rules). Because
// only Apply commits, an invalid draft can never corrupt stored data; the last
// applied (valid) value is preserved until the draft becomes valid again.
const invalidFields = computed<string[]>(() => {
  const out: string[] = []
  for (const [path, field] of fieldMap.value) {
    const v = localeValue(draftData.value, field)
    if (validateField(field, v) !== null) out.push(path)
  }
  return out
})

// ---- Status ------------------------------------------------------------
const status = computed(() => {
  if (applyState.value === 'applying') return 'Applying…'
  if (applyState.value === 'error') return 'Apply Failed'
  if (applyState.value === 'applied') return 'Applied'
  if (validationVisible.value && invalidFields.value.length) return 'Validation Error'
  if (isDirty.value) return dirtyCount.value ? `Unsaved Changes · ${dirtyCount.value}` : 'Unsaved Changes'
  return 'Saved'
})

const applyLabel = computed(() => {
  if (applyState.value === 'applying') return 'Applying…'
  if (applyState.value === 'applied') return '✓ Applied'
  return dirtyCount.value ? `Apply (${dirtyCount.value})` : 'Apply'
})

// ---- Value read / locale helpers --------------------------------------
function localeValue(source: Record<string, unknown>, field: SchemaField): unknown {
  const raw = getByPath(source, field.name, field.default)
  if (!field.translatable) return raw
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return (raw as Record<string, unknown>)[locale.value] ?? ''
  }
  return typeof raw === 'string' ? raw : ''
}

// The value the live preview should show. A responsive field stores an envelope but
// the iframe renders one breakpoint, so the patch carries the effective value for the
// active breakpoint; a token reference (26.7/26.9) is resolved to its concrete value so
// the iframe shows the real color/spacing rather than `{{…}}`. Non-responsive,
// non-token values pass through unchanged.
function previewValue(field: SchemaField | undefined, raw: unknown): unknown {
  const effective = field?.responsive ? resolveEffective(raw, activeBreakpoint.value) : raw
  return tokensRef.value.resolve(effective)
}

function emitFieldPatch(name: string, source: Record<string, unknown> = draftData.value): void {
  const field = fieldMap.value.get(name)
  const value = field ? localeValue(source, field) : getByPath(source, name)
  store.emitLivePatch({ componentId: activeId.value, type: selected.value?.type ?? '', field: name, value: previewValue(field, value) })
}

// A local edit clears transient Apply/validation feedback.
function onEdited(): void {
  if (applyState.value === 'applied' || applyState.value === 'error') applyState.value = 'idle'
  validationVisible.value = false
  if (appliedTimer !== null) {
    window.clearTimeout(appliedTimer)
    appliedTimer = null
  }
}

// ---- Field controller --------------------------------------------------
function readValue(field: SchemaField): unknown {
  return localeValue(draftData.value, field)
}

function writeValue(field: SchemaField, value: unknown): void {
  let toStore: unknown = value
  if (field.translatable) {
    const existing = getByPath(draftData.value, field.name)
    const base = existing && typeof existing === 'object' && !Array.isArray(existing) ? existing : {}
    toStore = { ...(base as Record<string, unknown>), [locale.value]: value }
  }
  draftData.value = setByPath(draftData.value, field.name, toStore)
  syncDirty(field.name)
  store.emitLivePatch({ componentId: activeId.value, type: selected.value?.type ?? '', field: field.name, value: previewValue(field, value) })
  onEdited()
}

// Keep a field's dirty flag in sync with whether the draft differs from applied.
function syncDirty(name: string): void {
  const changed = JSON.stringify(getByPath(draftData.value, name)) !== JSON.stringify(getByPath(appliedData.value, name))
  if (changed) dirtyFields.value.add(name)
  else dirtyFields.value.delete(name)
  dirtyFields.value = new Set(dirtyFields.value)
}

function fieldDirty(field: SchemaField): boolean {
  return dirtyFields.value.has(field.name)
}

// Revert ONE field to its applied value (no command).
function revertField(field: SchemaField): void {
  const applied = getByPath(appliedData.value, field.name)
  draftData.value = setByPath(draftData.value, field.name, clonePlain(applied))
  dirtyFields.value.delete(field.name)
  dirtyFields.value = new Set(dirtyFields.value)
  emitFieldPatch(field.name, appliedData.value)
  onEdited()
}

function countDirty(entries: SchemaEntry[]): number {
  let n = 0
  for (const entry of entries) {
    if (isGroup(entry)) n += countDirty(entry.fields)
    else if (dirtyFields.value.has(entry.name)) n++
  }
  return n
}

// ---- L3: Apply ---------------------------------------------------------
async function applyDraft(id: string | null = activeId.value): Promise<boolean> {
  if (!id || !isDirty.value) return true
  const data = clonePlain(draftData.value)
  if (JSON.stringify(data) === JSON.stringify(appliedData.value)) {
    dirtyFields.value = new Set()
    return true
  }
  applyState.value = 'applying'
  const ok = await store.updateComponentDataById(id, data)
  if (ok) {
    appliedData.value = clonePlain(data)
    dirtyFields.value = new Set()
    applyState.value = 'idle'
  } else {
    applyState.value = 'error'
  }
  return ok
}

// User-driven Apply: validate first, prevent duplicate submits, show ✓ Applied.
async function attemptApply(): Promise<boolean> {
  if (applyState.value === 'applying' || !isDirty.value) return false
  if (invalidFields.value.length) {
    validationVisible.value = true
    focusField.value = invalidFields.value[0]
    return false
  }
  const ok = await applyDraft()
  if (ok) {
    applyState.value = 'applied'
    if (appliedTimer !== null) window.clearTimeout(appliedTimer)
    appliedTimer = window.setTimeout(() => {
      if (applyState.value === 'applied') applyState.value = 'idle'
      appliedTimer = null
    }, 1000)
  }
  return ok
}

// ---- Cancel (§1) -------------------------------------------------------
// Discard all unapplied edits: restore the applied snapshot + preview, clear dirty
// and validation. Does not command, switch, or close.
function cancelDraft(): void {
  for (const name of dirtyFields.value) emitFieldPatch(name, appliedData.value)
  draftData.value = clonePlain(appliedData.value)
  dirtyFields.value = new Set()
  activePreset.value = null
  validationVisible.value = false
  applyState.value = 'idle'
}

// ---- Reset to defaults (§1/§2) ----------------------------------------
// Stage definition/preset defaults as a dirty draft (no command). Confirms first
// when the component actually has meaningful values to overwrite.
function resetDraft(): void {
  if (hasMeaningfulValues(appliedData.value, schema.value, locale.value)) {
    resetConfirm.value = true
    return
  }
  doReset()
}

function doReset(): void {
  resetConfirm.value = false
  if (!activeId.value) return
  const next = buildDefaultData({
    schema: schema.value,
    currentData: appliedData.value,
    locale: locale.value,
    presetData: activePresetData.value,
  })
  draftData.value = next
  const changed = diffFieldPaths(appliedData.value, next, schema.value)
  dirtyFields.value = new Set(changed)
  for (const path of changed) emitFieldPatch(path)
  onEdited()
}

// ---- Reseed on selection change ---------------------------------------
function reseed(id: string | null): void {
  const seed = clonePlain(store.selectedData)
  draftData.value = seed
  appliedData.value = clonePlain(seed)
  dirtyFields.value = new Set()
  activeId.value = id
  activePreset.value = null
  applyState.value = 'idle'
  validationVisible.value = false
  search.value = ''
}

watch(
  () => store.selectedComponentId,
  (newId, oldId) => {
    if (selectionGuard) {
      selectionGuard = false
      return
    }
    const leavingDirty = !!oldId && oldId !== newId && isDirty.value && activeId.value === oldId
    if (leavingDirty && newId) {
      pendingSwitchId.value = newId
      selectionGuard = true
      store.selectComponent(oldId)
      switchDialog.value = true
      return
    }
    if (leavingDirty && !newId) cancelDraft()
    reseed(newId)
  },
  { immediate: true },
)

// Switch dialog: Apply & Continue / Discard & Continue / Stay Here.
async function resolveSwitch(choice: 'apply' | 'discard' | 'stay'): Promise<void> {
  // Command dirty-guard branch (Increment 4.2) — a structural command is parked
  // on our promise. Resolve it instead of switching selection; reuses this dialog.
  if (commandGuardResolve) {
    const resolve = commandGuardResolve
    if (choice === 'stay') {
      switchDialog.value = false
      commandGuardResolve = null
      resolve(false)
      return
    }
    if (choice === 'apply') {
      const ok = await attemptApply()
      if (!ok) return // apply failed → keep the dialog open, command stays blocked
    } else {
      cancelDraft()
    }
    switchDialog.value = false
    commandGuardResolve = null
    resolve(true)
    return
  }
  if (choice === 'stay') {
    switchDialog.value = false
    pendingSwitchId.value = null
    return
  }
  const target = pendingSwitchId.value
  if (choice === 'apply') {
    const ok = await attemptApply()
    if (!ok) return // apply failed / invalid → do NOT switch, keep the dialog
  } else {
    cancelDraft()
  }
  switchDialog.value = false
  pendingSwitchId.value = null
  if (target) store.selectComponent(target)
}

// ---- Save Draft / Publish auto-apply (abort on failure) ----------------
const flushHook = (): Promise<boolean> => applyDraft()

// ---- Structural command dirty-guard (Increment 4.2) --------------------
// Clean draft → proceed silently. Staged draft → open the switch dialog and
// park until the user resolves it. No second dialog is created.
const commandGuard = (): Promise<boolean> => {
  if (!isDirty.value) return Promise.resolve(true)
  return new Promise<boolean>((resolve) => {
    commandGuardResolve = resolve
    switchDialog.value = true
  })
}
onMounted(() => {
  store.registerInspectorFlush(flushHook)
  store.registerInspectorGuard(commandGuard)
  window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  store.unregisterInspectorFlush(flushHook)
  store.unregisterInspectorGuard(commandGuard)
  // A command parked on us while we tear down must not hang: abort it.
  if (commandGuardResolve) {
    commandGuardResolve(false)
    commandGuardResolve = null
  }
  window.removeEventListener('keydown', onKeydown)
  if (appliedTimer !== null) window.clearTimeout(appliedTimer)
  if (isDirty.value) for (const name of dirtyFields.value) emitFieldPatch(name, appliedData.value)
  store.setInspectorDirty(false)
})
watch(isDirty, (v) => store.setInspectorDirty(v))

// Phase 26.6 — switching the active breakpoint repaints every responsive field in the
// live preview to that breakpoint's effective value (the iframe renders one at a time).
watch(activeBreakpoint, () => {
  if (!activeId.value) return
  for (const [name, field] of fieldMap.value) {
    if (field.responsive) emitFieldPatch(name)
  }
})

// ---- Keyboard (§17) ----------------------------------------------------
function focusInsideInspector(): boolean {
  const el = rootEl.value
  return !!el && el.contains(document.activeElement)
}

function revertFocusedField(): void {
  const host = (document.activeElement as HTMLElement | null)?.closest('[data-field]') as HTMLElement | null
  const name = host?.getAttribute('data-field')
  const field = name ? fieldMap.value.get(name) : undefined
  if (field) revertField(field)
}

function onKeydown(e: KeyboardEvent): void {
  if (!selected.value) return
  const mod = e.ctrlKey || e.metaKey

  if (mod && e.key === 'Enter') {
    e.preventDefault()
    void attemptApply()
    return
  }
  // Alt/Option+Backspace reverts the focused field to its applied value.
  if (e.altKey && e.key === 'Backspace' && focusInsideInspector()) {
    e.preventDefault()
    revertFocusedField()
    return
  }
  if (e.key === 'Escape') {
    if (resetConfirm.value) {
      e.preventDefault()
      resetConfirm.value = false
      return
    }
    if (switchDialog.value) {
      e.preventDefault()
      void resolveSwitch('stay')
      return
    }
    if (search.value) {
      e.preventDefault()
      search.value = ''
      return
    }
    if (isDirty.value && focusInsideInspector()) {
      e.preventDefault()
      cancelDraft()
    }
  }
}

const controller: FieldController = {
  getValue: readValue,
  setValue: writeValue,
  commit: () => {},
  revert: () => cancelDraft(),
  get locale() {
    return locale.value
  },
  fieldDirty,
  revertField,
  dirtyCount: countDirty,
}
provide(FieldControllerKey, controller)

// ---- Search view (render filter) --------------------------------------
function contains(text: unknown): boolean {
  return typeof text === 'string' && text.toLowerCase().includes(search.value.trim().toLowerCase())
}
function fieldVisible(field: SchemaField): boolean {
  if (!search.value.trim()) return true
  return contains(field.label) || contains(field.name) || contains(field.help)
}
function entryVisible(entry: SchemaEntry): boolean {
  if (!search.value.trim()) return true
  if (isGroup(entry)) {
    return contains(entry.label) || contains(entry.name) || entry.fields.some(entryVisible)
  }
  return fieldVisible(entry)
}
const view: InspectorView = {
  get query() {
    return search.value
  },
  get searching() {
    return !!search.value.trim()
  },
  fieldVisible,
  entryVisible,
}
provide(InspectorViewKey, view)

// Phase 26.9 — the style context adopted editors (StyleBoundField) resolve against.
// Built from the live token registry (26.7), the active breakpoint (26.6), the selected
// component, and the single style resolver (26.8). Provided here (not in the editors) so
// the editors stay store-free and independently testable.
provide(StyleContextKey, {
  tokens: () => tokensRef.value,
  breakpoint: () => activeBreakpoint.value,
  componentType: () => selected.value?.type ?? '',
  componentId: () => selected.value?.id,
  resolve: (query) => resolveComponentStyleValue(query, { tokens: tokensRef.value, breakpoint: activeBreakpoint.value }),
})

function action(fn: (id: string) => void) {
  const id = selected.value?.id
  if (id) fn(id)
}
</script>

<template>
  <div ref="rootEl" class="pb-inspector">
    <div v-if="!selected" class="pb-inspector__empty">
      <p>Select a component to edit its properties.</p>
    </div>

    <template v-else>
      <!-- Scrollable body: everything above the sticky action footer. -->
      <div class="pb-inspector__body">
        <header class="pb-inspector__head">
          <div>
            <h3 class="pb-inspector__title">
              {{ label }}
              <span
                v-if="isDirty"
                class="pb-inspector__title-dot"
                :title="`${dirtyCount} unapplied change${dirtyCount === 1 ? '' : 's'}`"
                aria-hidden="true"
              ></span>
            </h3>
            <p class="pb-inspector__status" :data-state="status" aria-live="polite">
              <span v-if="isDirty && applyState === 'idle'" class="pb-inspector__status-dot" aria-hidden="true"></span>
              {{ status }}
            </p>
          </div>
          <div class="pb-inspector__meta">
            <code class="pb-inspector__id">{{ selected.id }}</code>
          </div>
        </header>

        <p v-if="store.commandError" class="pb-inspector-warn">{{ store.commandError }}</p>

        <InspectorSearch v-if="hasFields" v-model="search" />

        <div v-if="presets.length" class="pb-inspector-presets">
          <span class="pb-inspector-presets__label">Presets</span>
          <div class="pb-inspector-presets__row">
            <button
              v-for="preset in presets"
              :key="preset.name"
              type="button"
              class="pb-inspector-presets__item"
              :class="{ 'is-active': preset.name === activePreset }"
              @click="applyPreset(preset)"
            >
              {{ preset.label }}
            </button>
          </div>
        </div>

        <InspectorTabs v-if="hasFields" :key="selected.id" :tabs="tabs" :focus-field="focusField" />
        <!-- PB-TEMPLATE-REGISTRY-2-D — an unknown (unregistered-type) node gets a read-only
             safety panel instead of a generic empty state: no schema form, no raw JSON, no
             data dump. The node itself is never mutated; editing returns when it is available. -->
        <UnknownNodePlaceholder
          v-else-if="store.selectedIsUnknown"
          variant="inspector"
          :type="selected.type"
          :kind="selected.kind"
        />
        <p v-else class="pb-inspector__empty">This component has no editable properties.</p>

        <footer class="pb-inspector__foot">
          <button class="pb-ui-btn pb-editor-btn" type="button" @click="action((id) => store.duplicateComponent(id))">
            Duplicate
          </button>
          <button class="pb-ui-btn pb-editor-btn pb-editor-btn--danger" type="button" @click="action((id) => store.deleteComponent(id))">
            Delete
          </button>
        </footer>
      </div>

      <!-- Sticky action footer — always visible (§5). -->
      <footer class="pb-inspector__apply-bar">
        <button
          class="pb-ui-btn pb-editor-btn pb-inspector__apply-btn"
          type="button"
          :disabled="!isDirty || applyState === 'applying'"
          aria-label="Discard unapplied changes"
          @click="cancelDraft"
        >
          Cancel
        </button>
        <button
          class="pb-ui-btn pb-editor-btn pb-inspector__apply-btn"
          type="button"
          :disabled="applyState === 'applying'"
          aria-label="Reset to default settings"
          @click="resetDraft"
        >
          Reset
        </button>
        <button
          class="pb-ui-btn pb-editor-btn pb-editor-btn--primary pb-inspector__apply-btn pb-inspector__apply-btn--primary"
          type="button"
          :disabled="!isDirty || applyState === 'applying'"
          :aria-label="applyLabel"
          @click="attemptApply"
        >
          {{ applyLabel }}
        </button>
      </footer>
    </template>

    <!-- Reset-to-defaults confirmation (§7). -->
    <div v-if="resetConfirm" class="pb-inspector-dialog" role="dialog" aria-modal="true" aria-label="Reset component">
      <div class="pb-inspector-dialog__box">
        <p class="pb-inspector-dialog__title">Reset this component to its default settings?</p>
        <div class="pb-inspector-dialog__actions">
          <button class="pb-ui-btn pb-editor-btn pb-editor-btn--primary" type="button" @click="doReset">Reset to Defaults</button>
          <button class="pb-ui-btn pb-editor-btn" type="button" @click="resetConfirm = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Unapplied-changes dialog on component switch (§15). -->
    <div v-if="switchDialog" class="pb-inspector-dialog" role="dialog" aria-modal="true" aria-label="Unapplied changes">
      <div class="pb-inspector-dialog__box">
        <p class="pb-inspector-dialog__title">You have unapplied changes for {{ label }}.</p>
        <p v-if="applyState === 'error'" class="pb-inspector-dialog__error">Apply Failed — resolve the error to continue.</p>
        <div class="pb-inspector-dialog__actions">
          <button class="pb-ui-btn pb-editor-btn pb-editor-btn--primary" type="button" @click="resolveSwitch('apply')">
            Apply &amp; Continue
          </button>
          <button class="pb-ui-btn pb-editor-btn pb-editor-btn--danger" type="button" @click="resolveSwitch('discard')">
            Discard &amp; Continue
          </button>
          <button class="pb-ui-btn pb-editor-btn" type="button" @click="resolveSwitch('stay')">Stay Here</button>
        </div>
      </div>
    </div>
  </div>
</template>
