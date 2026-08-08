<script setup lang="ts">
/**
 * PB-UI-1 primitive — Tabs (ARIA tablist with roving focus).
 *
 * Keyboard: ArrowLeft/ArrowRight move + select, Home/End jump to ends. The
 * active tab is the only one in the tab order (roving tabindex). Selection is
 * controlled via v-model; no business logic lives here.
 */
import { ref } from 'vue';

interface Tab {
  id: string;
  label: string;
}

const props = defineProps<{ tabs: Tab[]; modelValue: string }>();
const emit = defineEmits<{ (e: 'update:modelValue', id: string): void }>();

const tabEls = ref<HTMLButtonElement[]>([]);

function select(id: string): void {
  if (id !== props.modelValue) emit('update:modelValue', id);
}

function focusAt(index: number): void {
  const count = props.tabs.length;
  const next = (index + count) % count;
  const tab = props.tabs[next];
  if (!tab) return;
  select(tab.id);
  tabEls.value[next]?.focus();
}

function onKeydown(ev: KeyboardEvent, index: number): void {
  switch (ev.key) {
    case 'ArrowRight':
      ev.preventDefault();
      focusAt(index + 1);
      break;
    case 'ArrowLeft':
      ev.preventDefault();
      focusAt(index - 1);
      break;
    case 'Home':
      ev.preventDefault();
      focusAt(0);
      break;
    case 'End':
      ev.preventDefault();
      focusAt(props.tabs.length - 1);
      break;
    default:
      break;
  }
}
</script>

<template>
  <div class="pb-ui-tabs" role="tablist">
    <button
      v-for="(tab, index) in tabs"
      :key="tab.id"
      ref="tabEls"
      type="button"
      class="pb-ui-tab"
      role="tab"
      :aria-selected="tab.id === modelValue"
      :tabindex="tab.id === modelValue ? 0 : -1"
      @click="select(tab.id)"
      @keydown="onKeydown($event, index)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
