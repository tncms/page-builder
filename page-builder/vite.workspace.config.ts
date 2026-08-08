import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Phase 26.36 — a SECOND, route-scoped bundle for the Template Library admin workspace.
// Mirrors the editor build (same Vite infrastructure) but emits
// public/vendor/page-builder/workspace/workspace.{js,css}, referenced ONLY from the
// template-workspace Blade — never the editor bundle or the public frontend.
export default defineConfig({
  plugins: [vue()],
  // Library mode does NOT inject process.env.NODE_ENV, so Vue's source references
  // leak into the browser bundle and throw "process is not defined". Replace them
  // at build time (this also strips Vue's dev-only warning code).
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: fileURLToPath(new URL('../../public/vendor/page-builder/workspace', import.meta.url)),
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('resources/editor/workspace.ts', import.meta.url)),
      name: 'TNPageBuilderTemplateWorkspace',
      formats: ['iife'],
      fileName: () => 'workspace.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'workspace.[ext]',
      },
    },
  },
})
