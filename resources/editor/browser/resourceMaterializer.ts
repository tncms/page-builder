// PB-TEMPLATE-REGISTRY-2-C — bridge a selected Resource Browser resource to the
// EXISTING editor authorities. It reimplements nothing:
//   • a Pattern routes through the editor store's insert-pattern seam
//     (insertBuilderResourceByRid → CommandBus insert-pattern; one undo entry, ids
//     regenerated server-side exactly once).
//   • a Template goes plan → commit through the (unchanged) FullPageImportService,
//     then reloads the authoritative editor context (fresh draft + version token).
//
// Fail-closed everywhere: no owner ⇒ no request; a blocked plan never commits; a
// stale/failed commit NEVER reloads or adopts server state, so a false success or a
// partial mutation is impossible. Success adopts authoritative state ONCE.
import type { PatternInsertOutcome, ResourceMaterializer, TemplateApplyOutcome } from './types'

/** The minimal editor-store surface the materializer needs (a subset of the store). */
export interface EditorMaterializationBridge {
  owner: { type: string; id: number | string } | null
  commandError: string | null
  /** Insert an id-free resource tree via the existing insert-pattern/insert-component command. */
  /** PB-TREG-2 mint-at-insert: insert a registered resource server-side (state adopted,
   *  opaque provenance group stored). The one server insertion per registry insertion. */
  insertRegisteredResource(rid: string, placement: { targetParentId?: string | null; index?: number | null }): Promise<boolean>
  /** The current container a block would drop into (null → its host section). */
  blockInsertParent(): string | null
  /** Re-fetch the authoritative editor context and adopt its draft + version token. */
  loadContext(): Promise<void>
}

/** A minimal Full Page Template import client (thin wrappers over the existing endpoints). */
export interface TemplateImportClient {
  planTemplateImport(body: {
    rid: string
    ownerType: string
    ownerId: number | string
  }): Promise<{ ok: boolean; planHash: string; revision: string; blockers: Array<{ code: string; message: string }> }>
  commitTemplateImport(body: {
    rid: string
    ownerType: string
    ownerId: number | string
    planHash: string
    revision: string
  }): Promise<{ status: number; data: { ok: boolean; revision?: string; issues?: Array<{ code: string; message: string }> } }>
}

/** Optimistic-concurrency blocker codes the FullPageImportService returns on a stale commit. */
const CONFLICT_CODES = new Set(['stale_revision', 'plan_mismatch'])

export function createResourceMaterializer(deps: {
  editor: EditorMaterializationBridge
  api: TemplateImportClient
}): ResourceMaterializer {
  const { editor, api } = deps

  async function insertPattern(rid: string): Promise<PatternInsertOutcome> {
    // A browser Pattern is a section resource; the editor regenerates ids + records
    // exactly one undo entry. On failure the draft/history/token are untouched.
    const ok = await editor.insertRegisteredResource(rid, {})
    if (ok) return { status: 'inserted' }
    return { status: 'failed', message: editor.commandError ?? undefined }
  }

  async function insertBlock(rid: string): Promise<PatternInsertOutcome> {
    // A browser Block is a block resource; it routes through the SAME
    // insertBuilderResourceByRid authority (insert-component into the current
    // container, or its self-contained host section), which regenerates ids and
    // records one undo entry server-side. No second insertion API is introduced.
    const ok = await editor.insertRegisteredResource(rid, { targetParentId: editor.blockInsertParent() })
    if (ok) return { status: 'inserted' }
    return { status: 'failed', message: editor.commandError ?? undefined }
  }

  async function applyTemplate(rid: string): Promise<TemplateApplyOutcome> {
    const owner = editor.owner
    if (!owner) return { status: 'failed' }

    // 1. Dry run (read-only): resolves the resource + current draft revision server-side.
    const plan = await api.planTemplateImport({ rid, ownerType: owner.type, ownerId: owner.id })
    if (!plan.ok) {
      return { status: 'blocked', message: plan.blockers[0]?.message }
    }

    // 2. Atomic commit (draft only) guarded by the plan's optimistic-concurrency tokens.
    const commit = await api.commitTemplateImport({
      rid,
      ownerType: owner.type,
      ownerId: owner.id,
      planHash: plan.planHash,
      revision: plan.revision,
    })

    if (commit.status === 200 && commit.data.ok) {
      // 3. Adopt the authoritative draft + version token ONCE, only after success.
      await editor.loadContext()
      return { status: 'applied' }
    }

    // A typed optimistic-concurrency conflict preserves local work and asks for a
    // re-review — never an auto-reload/merge. Any other failure is a safe error.
    const code = commit.data.issues?.[0]?.code ?? ''
    if (CONFLICT_CODES.has(code)) {
      return { status: 'conflict', message: commit.data.issues?.[0]?.message }
    }
    return { status: 'failed', message: commit.data.issues?.[0]?.message }
  }

  return { insertPattern, insertBlock, applyTemplate }
}
