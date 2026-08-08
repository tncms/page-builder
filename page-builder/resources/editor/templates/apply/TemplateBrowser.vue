<!--
  Phase 26.23 — Template browse + apply surface (completes the Template v1 lifecycle).

  Composes the Phase 26.22 Picker with the apply confirmation flow WITHOUT modifying the Picker:
  it reads the picker store's `selection` (the 26.22 handoff) and, when one exists, offers an
  Apply action that opens the confirmation dialog. Apply is owned by the apply store → runtime;
  the Picker stays a pure library-discovery surface.
-->
<script setup lang="ts">
import TemplatePicker from '../picker/TemplatePicker.vue'
import { useTemplatePickerStore } from '../picker'
import TemplateApplyDialog from './TemplateApplyDialog.vue'
import { useTemplateApplyStore } from './templateApplyStore'

const picker = useTemplatePickerStore()
const apply = useTemplateApplyStore()

function requestApply(): void {
  if (picker.selection) apply.request(picker.selection)
}
</script>

<template>
  <div class="pb-tpl-browser">
    <TemplatePicker />

    <div v-if="picker.selection" class="pb-tpl-browser__bar">
      <span class="pb-tpl-browser__selected">{{ picker.selection.item.name }}</span>
      <button type="button" class="pb-tpl-browser__apply" @click="requestApply()">Apply template</button>
    </div>

    <p v-if="apply.feedback" class="pb-tpl-browser__feedback" role="status">{{ apply.feedback }}</p>

    <TemplateApplyDialog />
  </div>
</template>

<style scoped>
.pb-tpl-browser {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.pb-tpl-browser__bar {
  position: sticky;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.5rem 0.6rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.45rem;
}
.pb-tpl-browser__selected {
  font-size: 0.78rem;
  font-weight: 600;
  color: #1e293b;
}
.pb-tpl-browser__apply {
  padding: 0.35rem 0.8rem;
  font-size: 0.75rem;
  color: #ffffff;
  background: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 0.375rem;
  cursor: pointer;
}
.pb-tpl-browser__feedback {
  margin: 0;
  padding: 0.4rem 0.6rem;
  font-size: 0.72rem;
  color: #047857;
  background: #ecfdf5;
  border-radius: 0.375rem;
}
</style>
