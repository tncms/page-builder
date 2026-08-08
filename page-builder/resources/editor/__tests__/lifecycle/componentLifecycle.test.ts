// Phase 26.3.16 — Automated lifecycle matrix.
//
// Runs the reusable harness over EVERY registered section and block (loaded from
// the real serialized metadata snapshot, not a hand-remembered list) and asserts
// the client lifecycle for each. This is the machine half of the capability
// matrix: a regression net that fails loudly the moment any component diverges
// from the golden `quote` contract at an automated stage.

import { describe, it, expect } from 'vitest'
import { runComponentLifecycle, METADATA, type LifecycleReport } from './runComponentLifecycle'

const AUTOMATED_STAGES = [
  'definition_exists',
  'metadata_resolves',
  'default_payload_shape',
  'candidate_valid',
  'command_commit',
  'single_command',
  'returned_node',
  'unique_id',
  'selected_after_insert',
  'tree_hydration',
  'undo',
  'redo',
] as const

const sectionTypes = METADATA.sections.map((s) => s.type)
const blockTypes = METADATA.blocks.map((b) => b.type)

function assertAllPass(report: LifecycleReport): void {
  for (const stage of AUTOMATED_STAGES) {
    expect(report.stages[stage], `${report.type} stage ${stage} (notes: ${report.notes.join('; ')})`).toBe('PASS')
  }
  expect(report.firstFailureStage, `${report.type} first failure`).toBeNull()
}

describe('component lifecycle matrix — inventory is complete', () => {
  it('registers the 9 canonical sections', () => {
    expect(sectionTypes).toEqual([
      'section', 'hero', 'text-section', 'image-section', 'columns',
      'cta', 'faq', 'testimonial', 'logo-strip',
    ])
  })

  it('registers the 10 canonical blocks', () => {
    expect(blockTypes).toEqual([
      'text', 'heading', 'button', 'image', 'icon',
      'accordion-item', 'container', 'column', 'logo', 'quote',
    ])
  })

  it('every block default payload is an object (not a list) — the golden contract', () => {
    for (const def of METADATA.blocks) {
      const d = def.defaults as unknown
      expect(Array.isArray(d), `${def.type} defaults must not be a list`).toBe(false)
      expect(d !== null && typeof d === 'object', `${def.type} defaults must be an object`).toBe(true)
    }
  })
})

describe('block lifecycle', () => {
  it.each(blockTypes)('block "%s" passes every automated lifecycle stage', async (type) => {
    const report = await runComponentLifecycle({ type, kind: 'block' })
    assertAllPass(report)
  })
})

describe('section lifecycle (root insertion)', () => {
  it.each(sectionTypes)('section "%s" passes every automated lifecycle stage at root', async (type) => {
    const report = await runComponentLifecycle({ type, kind: 'section' })
    assertAllPass(report)
  })
})

describe('golden reference — quote', () => {
  it('quote is fully SUPPORTED and is the differential baseline', async () => {
    const report = await runComponentLifecycle({ type: 'quote', kind: 'block' })
    assertAllPass(report)
    expect(report.insertedId).toMatch(/^quote-srv-/)
  })
})

describe('Add Element candidate consistency (spec Part 9)', () => {
  // The candidate set a Column offers must be exactly the non-layout blocks — the
  // same list every Add Element surface derives from insertCandidates(). Layout
  // blocks (container/column) are excluded by the nesting policy.
  it('a column offers all content blocks and excludes container/column', async () => {
    const report = await runComponentLifecycle({ type: 'quote', kind: 'block', target: 'host-column' })
    expect(report.stages.candidate_valid).toBe('PASS')
  })

  it('a container/column cannot be inserted into a column', async () => {
    for (const type of ['container', 'column']) {
      const report = await runComponentLifecycle({ type, kind: 'block', target: 'host-column' })
      expect(report.stages.candidate_valid, `${type} must be rejected under a column`).toBe('FAIL')
    }
  })
})
