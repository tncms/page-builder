<script setup lang="ts">
// Fallback for field types the inspector doesn't render yet (richtext,
// responsive, …). Shows the type and stays disabled so no value is lost.
import type { SchemaField } from '../schema'
defineProps<{ field: SchemaField }>()
</script>

<template>
  <div class="pb-inspector-unsupported">
    <code>{{ field.type }}</code>
    <span>This field type isn’t editable here yet.</span>
  </div>
</template>
