import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'

/**
 * Builds the Phase 16 frontend editor IFRAME runtime — the tiny script injected
 * into the owner's real public page in editor mode. It ships NO Vue: it only
 * scans markers, reports interactions and applies shell updates over the bridge.
 *
 *   resources/frontend-editor/iframe/main.ts
 *     -> public/vendor/page-builder/frontend-editor/iframe.js
 *        public/vendor/page-builder/frontend-editor/iframe.css
 *
 * emptyOutDir is false so this appends to the shell bundle (built first) instead
 * of wiping it.
 */
export default defineConfig({
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: fileURLToPath(new URL('../../public/vendor/page-builder/frontend-editor', import.meta.url)),
    emptyOutDir: false,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('resources/frontend-editor/iframe/main.ts', import.meta.url)),
      name: 'TNPageBuilderFrontendIframe',
      formats: ['iife'],
      fileName: () => 'iframe.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'iframe.[ext]',
      },
    },
  },
})
