<script setup lang="ts">
// Phase 20 — Device switcher in the builder top bar. Changing device only resizes
// the iframe (shellStore.iframeWidth); no per-device editing yet.
import { useShellStore } from '../shellStore'
import { DEVICES, type Device } from '../device'

const shell = useShellStore()

function select(device: Device): void {
  shell.setDevice(device)
}
</script>

<template>
  <div class="pb-live-builder-device" role="group" aria-label="Preview device">
    <button
      v-for="device in DEVICES"
      :key="device.id"
      type="button"
      class="pb-live-builder-device__btn"
      :class="{ 'is-active': shell.device === device.id }"
      :aria-pressed="shell.device === device.id"
      :title="device.label"
      @click="select(device.id)"
    >
      <span aria-hidden="true">{{ device.icon }}</span>
    </button>
  </div>
</template>
