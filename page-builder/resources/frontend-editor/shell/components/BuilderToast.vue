<script setup lang="ts">
// Phase 26.3.18 — non-blocking builder toasts (intent guidance).
//
// A polite ARIA live region so screen readers announce the message; each toast has
// a visible dismiss control and an optional action button. Never traps focus,
// never blocks editing. Paired icon + text (no icon-only information).
import { useBuilderToastStore } from '../builderToastStore'

const toasts = useBuilderToastStore()
</script>

<template>
  <div class="pb-builder-toasts" role="status" aria-live="polite" aria-atomic="false">
    <div v-for="toast in toasts.toasts" :key="toast.id" class="pb-builder-toast">
      <span class="pb-builder-toast__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18">
          <circle cx="12" cy="12" r="9" />
          <path stroke-linecap="round" d="M12 8h.01M11 12h1v4h1" />
        </svg>
      </span>
      <p class="pb-builder-toast__message">{{ toast.message }}</p>
      <button
        v-if="toast.action"
        type="button"
        class="pb-builder-toast__action"
        @click="toast.action.run(); toasts.dismiss(toast.id)"
      >
        {{ toast.action.label }}
      </button>
      <button
        type="button"
        class="pb-builder-toast__close"
        aria-label="Dismiss notification"
        @click="toasts.dismiss(toast.id)"
      >
        ×
      </button>
    </div>
  </div>
</template>

<style scoped>
.pb-builder-toasts {
  position: absolute;
  bottom: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 60;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  pointer-events: none;
  max-width: min(90vw, 34rem);
}

.pb-builder-toast {
  pointer-events: auto;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.6rem 0.75rem;
  border-radius: 10px;
  background: #0f172a;
  color: #f8fafc;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.28);
  font-size: 13px;
  line-height: 1.35;
}

.pb-builder-toast__icon {
  display: inline-flex;
  flex: 0 0 auto;
  color: #7dd3fc;
}

.pb-builder-toast__message {
  margin: 0;
  flex: 1 1 auto;
}

.pb-builder-toast__action {
  flex: 0 0 auto;
  border: 1px solid #38bdf8;
  background: transparent;
  color: #7dd3fc;
  font-size: 12px;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
}

.pb-builder-toast__action:hover {
  background: rgba(56, 189, 248, 0.14);
}

.pb-builder-toast__close {
  flex: 0 0 auto;
  border: none;
  background: transparent;
  color: #94a3b8;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 0 0.15rem;
}

.pb-builder-toast__close:hover {
  color: #f8fafc;
}
</style>
