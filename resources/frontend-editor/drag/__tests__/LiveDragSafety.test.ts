import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../../../editor/stores/editor'
import { useNavigatorLocksStore } from '../../../editor/stores/navigatorLocks'
import { useLiveDragStore } from '../LiveDragManager'
import type { EditorMetadata, LayoutPayload } from '../../../editor/types'
import type { LiveDragSubject, LiveDropResolution } from '../types'

// The live/iframe drop previously committed with NO lock and NO dirty-guard checks
// (Increment 7 audit gap). These tests prove it now shares the editor's guardDrop:
// a locked container and a blocking inspector draft both abort the commit, while a
// clean move still fires exactly one command.

function scene() {
  setActivePinia(createPinia())
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'x',
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [
      { id: 'b1', type: 'heading', data: {} },
      { id: 'b2', type: 'heading', data: {} },
    ] }],
  } as unknown as LayoutPayload
  editor.metadata = { version: '1', sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }], tokens: {} } as unknown as EditorMetadata
  const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
  const live = useLiveDragStore()
  return { editor, live, spy }
}

const subject = (): LiveDragSubject => ({ id: 'b2', kind: 'block', type: 'heading', label: '', icon: null, parentId: 'sec', index: 1 })
const resolution = (): LiveDropResolution => ({ valid: true, reason: null, edge: 'after', targetId: 'b1', referenceId: 'b1', parentId: 'sec', markerRect: null })

describe('LiveDragManager.drop safety', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('does NOT commit into a locked container', async () => {
    const { editor, live, spy } = scene()
    useNavigatorLocksStore().lock(editor.owner, 'sec')
    live.subject = subject()
    await live.drop(resolution())
    expect(spy).not.toHaveBeenCalled()
  })

  it('does NOT commit when the inspector dirty-guard blocks', async () => {
    const { editor, live, spy } = scene()
    editor.setInspectorDirty(true)
    editor.registerInspectorGuard(async () => false)
    live.subject = subject()
    await live.drop(resolution())
    expect(spy).not.toHaveBeenCalled()
  })

  it('commits exactly one move-component on a clean drop', async () => {
    const { editor, live, spy } = scene()
    live.subject = subject()
    await live.drop(resolution())
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', expect.objectContaining({ component_id: 'b2', target_parent_id: 'sec' }))
  })
})
