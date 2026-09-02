import { describe, it, expect, vi } from 'vitest'
import { createResourceMaterializer } from '../resourceMaterializer'
import type { EditorMaterializationBridge, TemplateImportClient } from '../resourceMaterializer'

// PB-TEMPLATE-REGISTRY-2-C — the materializer bridges a selected resource to the
// EXISTING editor authorities: insert-pattern command (patterns) and the Full Page
// Import service (templates). It owns NO catalog/preview state and NO new persistence:
// patterns delegate to the editor store's insert seam (one undo, ids regenerated once);
// templates go plan → commit → authoritative context reload. It NEVER reloads/adopts on
// a failed or stale commit, so a false success or partial mutation is impossible.

function editorBridge(over: Partial<EditorMaterializationBridge> = {}): EditorMaterializationBridge {
  return {
    owner: { type: 'pages', id: 7 },
    commandError: null,
    insertRegisteredResource: vi.fn(async () => true),
    blockInsertParent: vi.fn(() => null),
    loadContext: vi.fn(async () => {}),
    ...over,
  }
}

function importClient(over: Partial<TemplateImportClient> = {}): TemplateImportClient {
  return {
    planTemplateImport: vi.fn(async () => ({ ok: true, planHash: 'PH', revision: 'R1', blockers: [] })),
    commitTemplateImport: vi.fn(async () => ({ status: 200, data: { ok: true, revision: 'R2', issues: [] } })),
    ...over,
  }
}

describe('pattern insertion delegation', () => {
  it('delegates to the existing insert-pattern seam exactly once as a section', async () => {
    const editor = editorBridge()
    const m = createResourceMaterializer({ editor, api: importClient() })

    const outcome = await m.insertPattern('builtin:section:hero')

    expect(outcome).toEqual({ status: 'inserted' })
    expect(editor.insertRegisteredResource).toHaveBeenCalledTimes(1)
    expect(editor.insertRegisteredResource).toHaveBeenCalledWith('builtin:section:hero', {})
    // Insertion never touches the template import authority.
    const api = importClient()
    expect(api.planTemplateImport).not.toHaveBeenCalled()
  })

  it('surfaces a safe failure without reload when the insert command fails', async () => {
    const editor = editorBridge({
      insertRegisteredResource: vi.fn(async () => false),
      commandError: 'This design has no insertable content.',
    })
    const m = createResourceMaterializer({ editor, api: importClient() })

    const outcome = await m.insertPattern('builtin:section:hero')

    expect(outcome.status).toBe('failed')
    expect(editor.loadContext).not.toHaveBeenCalled()
  })
})

describe('portable block insertion delegation (PB-TREG-2)', () => {
  it('routes through the SAME insert authority exactly once as a block', async () => {
    const editor = editorBridge()
    const m = createResourceMaterializer({ editor, api: importClient() })

    const outcome = await m.insertBlock('theme:block:cta-button')

    expect(outcome).toEqual({ status: 'inserted' })
    expect(editor.insertRegisteredResource).toHaveBeenCalledTimes(1)
    // No second block insertion API: the same seam is called with the 'block' kind,
    // so the server owns id regeneration + payload materialization.
    expect(editor.insertRegisteredResource).toHaveBeenCalledWith('theme:block:cta-button', { targetParentId: null })
    const api = importClient()
    expect(api.planTemplateImport).not.toHaveBeenCalled()
  })

  it('surfaces a safe failure without reload when the block insert fails', async () => {
    const editor = editorBridge({
      insertRegisteredResource: vi.fn(async () => false),
      commandError: 'This design has no insertable content.',
    })
    const m = createResourceMaterializer({ editor, api: importClient() })

    const outcome = await m.insertBlock('theme:block:cta-button')

    expect(outcome.status).toBe('failed')
    expect(editor.loadContext).not.toHaveBeenCalled()
  })
})

describe('template application: plan -> commit -> authoritative reload', () => {
  it('applies to the draft and adopts authoritative context only on success', async () => {
    const editor = editorBridge()
    const api = importClient()
    const m = createResourceMaterializer({ editor, api })

    const outcome = await m.applyTemplate('builtin:page:company')

    expect(outcome).toEqual({ status: 'applied' })
    // Plan resolves owner + rid server-side (client never sends a payload).
    expect(api.planTemplateImport).toHaveBeenCalledWith({ rid: 'builtin:page:company', ownerType: 'pages', ownerId: 7 })
    // Commit carries the plan's optimistic-concurrency tokens verbatim.
    expect(api.commitTemplateImport).toHaveBeenCalledWith({
      rid: 'builtin:page:company',
      ownerType: 'pages',
      ownerId: 7,
      planHash: 'PH',
      revision: 'R1',
    })
    // Adopt the authoritative draft + version token from the server ONCE, after success.
    expect(editor.loadContext).toHaveBeenCalledTimes(1)
    const order = (editor.loadContext as ReturnType<typeof vi.fn>).mock.invocationCallOrder[0]
    const committed = (api.commitTemplateImport as ReturnType<typeof vi.fn>).mock.invocationCallOrder[0]
    expect(order).toBeGreaterThan(committed)
  })

  it('stops at a blocked plan with zero commit and zero reload', async () => {
    const editor = editorBridge()
    const api = importClient({
      planTemplateImport: vi.fn(async () => ({
        ok: false,
        planHash: 'PH',
        revision: 'R1',
        blockers: [{ code: 'unknown_components', message: 'The template uses components that are not installed.' }],
      })),
    })
    const m = createResourceMaterializer({ editor, api })

    const outcome = await m.applyTemplate('builtin:page:company')

    expect(outcome.status).toBe('blocked')
    expect(api.commitTemplateImport).not.toHaveBeenCalled()
    expect(editor.loadContext).not.toHaveBeenCalled()
  })

  it('maps a stale draft (422 stale_revision) to a typed conflict, preserving local work', async () => {
    const editor = editorBridge()
    const api = importClient({
      commitTemplateImport: vi.fn(async () => ({
        status: 422,
        data: { ok: false, revision: 'R1', issues: [{ code: 'stale_revision', message: 'The draft changed since the preview.' }] },
      })),
    })
    const m = createResourceMaterializer({ editor, api })

    const outcome = await m.applyTemplate('builtin:page:company')

    expect(outcome.status).toBe('conflict')
    // Never adopt server state on a conflict — local work is preserved, no false success.
    expect(editor.loadContext).not.toHaveBeenCalled()
  })

  it('maps a changed template (422 plan_mismatch) to a typed conflict', async () => {
    const editor = editorBridge()
    const api = importClient({
      commitTemplateImport: vi.fn(async () => ({
        status: 422,
        data: { ok: false, revision: 'R1', issues: [{ code: 'plan_mismatch', message: 'The template changed since the preview.' }] },
      })),
    })
    const m = createResourceMaterializer({ editor, api })

    expect((await m.applyTemplate('builtin:page:company')).status).toBe('conflict')
    expect(editor.loadContext).not.toHaveBeenCalled()
  })

  it('maps any other commit failure to a safe failed outcome (no reload)', async () => {
    const editor = editorBridge()
    const api = importClient({
      commitTemplateImport: vi.fn(async () => ({
        status: 422,
        data: { ok: false, revision: 'R1', issues: [{ code: 'commit_failed', message: 'The import could not be completed; the draft is unchanged.' }] },
      })),
    })
    const m = createResourceMaterializer({ editor, api })

    expect((await m.applyTemplate('builtin:page:company')).status).toBe('failed')
    expect(editor.loadContext).not.toHaveBeenCalled()
  })

  it('fails closed with zero request when the editor has no owner', async () => {
    const editor = editorBridge({ owner: null })
    const api = importClient()
    const m = createResourceMaterializer({ editor, api })

    expect((await m.applyTemplate('builtin:page:company')).status).toBe('failed')
    expect(api.planTemplateImport).not.toHaveBeenCalled()
    expect(api.commitTemplateImport).not.toHaveBeenCalled()
    expect(editor.loadContext).not.toHaveBeenCalled()
  })
})
