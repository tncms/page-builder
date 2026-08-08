// Phase 26.47E — bridge the Design Library / picker `page-builder:insert-resource`
// intent event (emitted since Phase 26.47B) to the Builder's native resource
// insertion. The picker emits { rid, kind }; we route it through the store, which
// inserts via the EXISTING insert-pattern / insert-component commands. This gives
// the 26.47B section/block picker its first editor consumer — no new insertion
// engine, no BuilderState/CommandBus change.

import type { useEditorStore } from '../stores/editor'

type EditorStore = ReturnType<typeof useEditorStore>

export const INSERT_RESOURCE_EVENT = 'page-builder:insert-resource'

/** Wire the document-level insert-resource listener. Returns an unsubscribe fn. */
export function wireBuilderResourceInsertion(store: EditorStore): () => void {
  const handler = (event: Event) => {
    const detail = (event as CustomEvent).detail as { rid?: unknown; kind?: unknown } | null
    const rid = typeof detail?.rid === 'string' ? detail.rid : ''
    const kind = typeof detail?.kind === 'string' ? detail.kind : ''
    if (!rid || (kind !== 'section' && kind !== 'block')) return
    void store.insertBuilderResourceByRid(rid, kind)
  }

  document.addEventListener(INSERT_RESOURCE_EVENT, handler)
  return () => document.removeEventListener(INSERT_RESOURCE_EVENT, handler)
}
