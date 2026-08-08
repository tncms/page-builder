import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Phase 13 — test runner for the geometry engine and overlays. Separate from
// vite.config.ts (which is library/IIFE build config) so the editor bundle is
// unaffected. happy-dom gives components a DOM; geometry unit tests inject
// their own layout, so they don't depend on real box measurement.
export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'happy-dom',
    include: ['resources/editor/**/*.test.ts', 'resources/frontend-editor/**/*.test.ts', 'resources/shared-ui/**/*.test.ts'],
    globals: true,
  },
})
