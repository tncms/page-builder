<script setup lang="ts">
/**
 * PB-PUBLISH-R1-H1 (Defect 2) — conflict reconciliation banner.
 *
 * The editor's only supported recovery from a typed version conflict (INV-PUB-06).
 * It renders ONLY while a real conflict is flagged, states the situation truthfully,
 * and offers an explicit "Reload latest version" action. The action is NOT silent:
 * it first requires the user to confirm that the current unsaved changes will be
 * replaced. Until they confirm, the stale local work stays present and recoverable.
 * On confirmation it delegates to store.reconcile(), which fetches the newest
 * authoritative context and atomically replaces state + token (never location.reload,
 * never an unload-guard bypass, never a silent auto-merge). A failed reload keeps the
 * local work and shows a truthful error, so nothing is lost.
 */
import { ref } from 'vue'
import { useEditorStore } from '../stores/editor'
import PbNotice from '../../shared-ui/PbNotice.vue'

const store = useEditorStore()

// Two-step gate: the first click arms the confirmation; only a second, explicit
// confirm replaces local work. Reset whenever the conflict clears.
const confirming = ref(false)

function arm() {
  confirming.value = true
}

function cancel() {
  confirming.value = false
}

async function confirmReload() {
  const ok = await store.reconcile()
  // On success the conflict clears and this banner unmounts. On failure the conflict
  // and local work are preserved; drop back to the initial (armed-off) state so the
  // truthful error is visible and the user can retry or keep editing.
  confirming.value = false
  return ok
}
</script>

<template>
  <PbNotice
    v-if="store.conflict"
    variant="warning"
    emphasis="solid"
    class="pb-conflict-reconciler"
    data-pb-conflict
  >
    <div class="pb-conflict-reconciler__body">
      <p class="pb-conflict-reconciler__message">
        {{ store.error || 'This layout changed in another session.' }}
      </p>

      <template v-if="!confirming">
        <div class="pb-conflict-reconciler__actions">
          <button
            type="button"
            class="pb-conflict-reconciler__reload"
            data-pb-conflict-reload
            :disabled="store.reconciling"
            @click="arm"
          >
            {{ store.reconciling ? 'Reloading…' : 'Reload latest version' }}
          </button>
        </div>
      </template>

      <template v-else>
        <p class="pb-conflict-reconciler__warn" data-pb-conflict-warning>
          Reloading replaces your current unsaved changes with the latest saved version. This cannot be undone.
        </p>
        <div class="pb-conflict-reconciler__actions">
          <button
            type="button"
            class="pb-conflict-reconciler__confirm"
            data-pb-conflict-confirm
            :disabled="store.reconciling"
            @click="confirmReload"
          >
            {{ store.reconciling ? 'Reloading…' : 'Replace my changes and reload' }}
          </button>
          <button
            type="button"
            class="pb-conflict-reconciler__cancel"
            data-pb-conflict-cancel
            :disabled="store.reconciling"
            @click="cancel"
          >
            Keep editing
          </button>
        </div>
      </template>
    </div>
  </PbNotice>
</template>
