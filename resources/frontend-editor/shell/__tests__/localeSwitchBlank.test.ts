import { describe, it, expect, vi } from 'vitest'
import { blankDecisionFrom, createBlankAndOpen } from '../localeSwitch'

// PB-CORE-AUTHORITY-REMEDIATION-R1 — the blank-layout decision comes only from the server.
const URL = '/api/page-builder/editor/context/page/1/translations'

describe('blankDecisionFrom', () => {
  it('reads the decision only from the server refusal code', () => {
    const refusal = {
      code: 'pb_blank_layout_decision_required',
      current: { locale: 'vi', source_layout: 'empty', return_url: '/admin/pages/1/edit' },
    }

    expect(blankDecisionFrom(refusal, URL)).toEqual({
      locale: 'vi',
      sourceLayout: 'empty',
      returnUrl: '/admin/pages/1/edit',
      createUrl: URL,
    })
    expect(blankDecisionFrom({ code: 'page_builder.layout.version_conflict', current: {} }, URL)).toBeNull()
    expect(blankDecisionFrom(new Error('boom'), URL)).toBeNull()
    expect(blankDecisionFrom(null, URL)).toBeNull()
  })

  it('treats a missing or empty return URL as unavailable', () => {
    expect(blankDecisionFrom({ code: 'pb_blank_layout_decision_required', current: { return_url: '' } }, URL)?.returnUrl).toBeNull()
    expect(blankDecisionFrom({ code: 'pb_blank_layout_decision_required', current: null }, URL)?.returnUrl).toBeNull()
  })
})

describe('createBlankAndOpen', () => {
  const decision = { locale: 'vi', sourceLayout: 'absent', returnUrl: null, createUrl: URL }

  it('posts the explicit blank choice, then opens the returned editor', async () => {
    const create = vi.fn(async () => '/pb/page/1/frontend?locale=vi')
    const navigate = vi.fn()

    await createBlankAndOpen(decision, { create, navigate })

    expect(create).toHaveBeenCalledWith(URL, 'vi', 'blank')
    expect(navigate).toHaveBeenCalledWith('/pb/page/1/frontend?locale=vi')
  })

  it('never navigates when the blank create fails', async () => {
    const navigate = vi.fn()
    const create = vi.fn(async (): Promise<string | null> => {
      throw new Error('nope')
    })

    await expect(createBlankAndOpen(decision, { create, navigate })).rejects.toThrow('nope')
    expect(navigate).not.toHaveBeenCalled()
  })
})
