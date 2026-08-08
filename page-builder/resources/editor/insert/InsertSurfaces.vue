<script setup lang="ts">
// Phase 26.38 — host overlays that make the routed Insert experiences visible.
//
// The Pattern Picker and Template Browser are plain panels with no modal chrome of their own. This
// host wraps the EXISTING components in a dismissible overlay, gated by their own open state, so the
// Insert router can surface them without changing them. It adds chrome only — no picker logic, no
// resolve, no command, no BuilderState. (Assets already ship their own modal; blocks live in the
// left panel.)
import { usePatternPickerStore } from '../patterns/picker/patternPickerStore'
import PatternPicker from '../patterns/picker/PatternPicker.vue'
import TemplateBrowser from '../templates/apply/TemplateBrowser.vue'
import { useInsertSurfacesStore } from './insertSurfaces'

const patternPicker = usePatternPickerStore()
const surfaces = useInsertSurfacesStore()
</script>

<template>
  <div class="pb-insert-surfaces">
    <!-- Patterns → existing Pattern Picker (Insert → Pattern Picker → insert-pattern). -->
    <div
      v-if="patternPicker.open"
      class="pb-insert-surface"
      role="dialog"
      aria-modal="true"
      aria-label="Insert a pattern"
      @keydown.esc="patternPicker.closePicker()"
      @click.self="patternPicker.closePicker()"
    >
      <div class="pb-insert-surface__panel">
        <header class="pb-insert-surface__head">
          <h2 class="pb-insert-surface__title">Patterns</h2>
          <button
            type="button"
            class="pb-insert-surface__close"
            aria-label="Close"
            @click="patternPicker.closePicker()"
          >
            ×
          </button>
        </header>
        <PatternPicker />
      </div>
    </div>

    <!-- Templates → existing Template Browser (Insert → Template Usage → Apply UX → Runtime). -->
    <div
      v-if="surfaces.templatesOpen"
      class="pb-insert-surface"
      role="dialog"
      aria-modal="true"
      aria-label="Insert a template"
      @keydown.esc="surfaces.closeTemplates()"
      @click.self="surfaces.closeTemplates()"
    >
      <div class="pb-insert-surface__panel">
        <header class="pb-insert-surface__head">
          <h2 class="pb-insert-surface__title">Templates</h2>
          <button
            type="button"
            class="pb-insert-surface__close"
            aria-label="Close"
            @click="surfaces.closeTemplates()"
          >
            ×
          </button>
        </header>
        <TemplateBrowser />
      </div>
    </div>
  </div>
</template>

<style scoped>
.pb-insert-surface {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3rem 1rem 1rem;
  background: rgba(15, 23, 42, 0.5);
  z-index: 60;
  overflow: auto;
}
.pb-insert-surface__panel {
  width: min(680px, 100%);
  background: #fff;
  border-radius: 0.6rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  padding: 1rem 1.25rem 1.25rem;
}
.pb-insert-surface__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}
.pb-insert-surface__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.pb-insert-surface__close {
  border: 0;
  background: transparent;
  font-size: 1.4rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
}
</style>
