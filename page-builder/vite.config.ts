import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

/**
 * Builds the Page Builder editor shell (Phase 9) into a single self-mounting
 * IIFE bundle plus one stylesheet, emitted to the app's public asset directory
 * so Laravel can serve them directly. The Blade editor page references these
 * two files ONLY — the bundle is never loaded globally or on the frontend.
 *
 *   resources/editor/main.ts  ->  public/vendor/page-builder/editor/editor.js
 *                                 public/vendor/page-builder/editor/editor.css
 */
export default defineConfig({
  plugins: [vue()],
  // Library mode does NOT inject process.env.NODE_ENV, so Vue's source references
  // leak into the browser bundle and throw "process is not defined". Replace them
  // at build time (this also strips Vue's dev-only warning code).
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: fileURLToPath(new URL('../../public/vendor/page-builder/editor', import.meta.url)),
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('resources/editor/main.ts', import.meta.url)),
      name: 'TNPageBuilderEditor',
      formats: ['iife'],
      fileName: () => 'editor.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'editor.[ext]',
      },
    },
  },
})
