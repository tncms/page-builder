<script setup lang="ts">
/**
 * PB-UI-1 primitive — Dialog (modal).
 *
 * role="dialog" aria-modal, Escape closes, focus is trapped within the panel
 * while open and restored to the previously focused element on close. No
 * persistence/owner/locale logic — emits `close`; the caller owns state.
 */
import { nextTick, ref, watch } from 'vue';

const props = withDefaults(
  defineProps<{ open: boolean; title?: string }>(),
  { title: undefined },
);
const emit = defineEmits<{ (e: 'close'): void }>();

const panel = ref<HTMLElement | null>(null);
let lastFocused: HTMLElement | null = null;

function focusables(): HTMLElement[] {
  if (!panel.value) return [];
  return Array.from(
    panel.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
    ),
  );
}

function onKeydown(ev: KeyboardEvent): void {
  if (ev.key === 'Escape') {
    ev.preventDefault();
    emit('close');
    return;
  }
  if (ev.key !== 'Tab') return;

  const items = focusables();
  if (items.length === 0) {
    ev.preventDefault();
    return;
  }
  const first = items[0];
  const last = items[items.length - 1];
  const active = document.activeElement as HTMLElement | null;

  if (ev.shiftKey && active === first) {
    ev.preventDefault();
    last.focus();
  } else if (!ev.shiftKey && active === last) {
    ev.preventDefault();
    first.focus();
  }
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      lastFocused = document.activeElement as HTMLElement | null;
      await nextTick();
      (focusables()[0] ?? panel.value)?.focus();
    } else {
      lastFocused?.focus();
      lastFocused = null;
    }
  },
);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="pb-ui-dialog__overlay"
      @keydown="onKeydown"
      @click.self="emit('close')"
    >
      <div
        ref="panel"
        class="pb-ui-dialog"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        tabindex="-1"
      >
        <header v-if="title" class="pb-ui-dialog__header">
          <h2 class="pb-ui-dialog__title">{{ title }}</h2>
        </header>
        <div class="pb-ui-dialog__body">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="pb-ui-dialog__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
