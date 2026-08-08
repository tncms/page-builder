import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

/**
 * Builds the Phase 16 frontend editor SHELL (the admin-side app that hosts the
 * iframe, top bar, dock and inspector) into a self-mounting IIFE bundle plus one
 * stylesheet, emitted next to the iframe bundle. Referenced ONLY from the
 * frontend-editor Filament page — never globally, never on the public frontend.
 *
 *   resources/frontend-editor/shell/main.ts
 *     -> public/vendor/page-builder/frontend-editor/shell.js
 *        public/vendor/page-builder/frontend-editor/shell.css
 *
 * emptyOutDir is FALSE here: the shell and iframe bundles share this directory and
 * every output filename is fixed (shell.js/css, iframe.js/css), so each build just
 * overwrites its own files. Emptying the dir made a standalone shell build silently
 * DELETE the iframe bundle (iframe.js/iframe.css → 404, "editor bridge did not
 * initialize"). Not emptying means neither partial build can orphan the other.
 */
export default defineConfig({
  plugins: [vue()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: fileURLToPath(new URL('../../public/vendor/page-builder/frontend-editor', import.meta.url)),
    emptyOutDir: false,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('resources/frontend-editor/shell/main.ts', import.meta.url)),
      name: 'TNPageBuilderFrontendShell',
      formats: ['iife'],
      fileName: () => 'shell.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'shell.[ext]',
      },
    },
  },
})
