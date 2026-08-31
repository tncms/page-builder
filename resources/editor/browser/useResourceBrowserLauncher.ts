// PB-TEMPLATE-REGISTRY-2-B / -2-C — the thin launch adapter both the Structure Builder and
// the Frontend/Live Builder use to open the ONE shared Resource Browser. It opens the shared
// store, lazily loads the metadata-only catalog through the shared editor client, and (R2-C)
// binds the safe-materialization seam + preview config — no second browser state, no eager
// boot, no mutation on open.
import { useEditorStore } from '../stores/editor'
import { useResourceBrowserStore } from './resourceBrowserStore'
import { createResourceMaterializer, type EditorMaterializationBridge, type TemplateImportClient } from './resourceMaterializer'
import type { ResourceKind } from './types'

export function useResourceBrowserLauncher() {
  const browser = useResourceBrowserStore()

  async function open(kind: ResourceKind = 'pattern'): Promise<void> {
    const editor = useEditorStore()

    // PB-TEMPLATE-REGISTRY-2-C — bind materialization + preview at open time, when the
    // editor is fully bootstrapped (client + boot present). Idempotent; delegates to the
    // EXISTING insert-pattern command and Full Page Import service.
    if (editor.client) {
      browser.bindMaterializer(
        createResourceMaterializer({
          editor: editor as unknown as EditorMaterializationBridge,
          api: editor.client as unknown as TemplateImportClient,
        }),
      )
    }
    browser.configurePreview({
      previewBase: editor.boot?.api?.resourcePreview ?? '',
      locale: editor.boot?.locale ?? '',
    })

    browser.openBrowser(kind)
    if (editor.client) await browser.load(editor.client)
  }

  return { browser, open }
}
