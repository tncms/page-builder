// Editor entry point (Phase 9). Reads the server-provided boot payload from
// window.TN_PAGE_BUILDER_BOOT, wires Pinia + the store, and mounts the shell.
// It self-executes; nothing here runs on the public frontend because the bundle
// is only referenced from the editor Blade page.

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { useEditorStore } from './stores/editor'
import { registerBuiltinPacks } from './templates/builtin'
import { registerStarterPacks } from './templates/starterPacks'
import { registerBuilderInsertProviders } from './insert'
import { wireBuilderResourceInsertion } from './insert/builderResourceInsertion'
import type { BootData } from './types'
import '../shared-ui/index.css'
import './editor.css'

declare global {
  interface Window {
    TN_PAGE_BUILDER_BOOT?: BootData
  }
}

function boot(): void {
  const data = window.TN_PAGE_BUILDER_BOOT
  const el = document.querySelector<HTMLElement>(data?.mount ?? '#pb-editor-root')

  if (!data || !el) {
    return
  }

  const app = createApp(App)
  app.use(createPinia())

  const store = useEditorStore()
  store.bootstrap(data)

  // Register first-party built-in template packs (resolvable + discoverable). Non-critical:
  // a bad pack must never block the editor from mounting, so failures degrade gracefully.
  try {
    registerBuiltinPacks({ freeze: true })
    registerStarterPacks({ freeze: true })
  } catch {
    /* built-in templates / starter packs simply won't appear */
  }

  // Wire the four unified Insert providers to the real Builder surfaces (Phase 26.38). Router only:
  // opening a category launches an existing experience; it never inserts. Non-critical to boot.
  try {
    registerBuilderInsertProviders()
  } catch {
    /* the Insert launcher simply won't list categories */
  }

  // Phase 26.47E — consume the picker's insert-resource intent, inserting design
  // resources natively via the existing commands. Non-critical to boot.
  try {
    wireBuilderResourceInsertion(store)
  } catch {
    /* the picker → native insertion bridge simply won't be wired */
  }

  el.innerHTML = ''
  app.mount(el)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot)
} else {
  boot()
}
