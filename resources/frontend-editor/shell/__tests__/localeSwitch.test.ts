import { describe, it, expect, vi } from 'vitest'
import {
  decideLocaleSwitch,
  isOpenable,
  openLocaleTarget,
  saveAndSwitch,
  switchWithoutSaving,
  type TranslationLocaleOption,
} from '../localeSwitch'

const opt = (over: Partial<TranslationLocaleOption> = {}): TranslationLocaleOption => ({
  locale: 'vi',
  label: 'Tiếng Việt',
  is_source: false,
  is_current: false,
  has_translation: true,
  has_layout: true,
  editor_url: '/pb/page/1/frontend?locale=vi',
  can_create: false,
  create_url: null,
  ...over,
})

// A locale with no Core translation yet, which the user may create.
const creatable = (over: Partial<TranslationLocaleOption> = {}): TranslationLocaleOption =>
  opt({
    has_translation: false,
    has_layout: false,
    editor_url: null,
    can_create: true,
    create_url: '/api/page-builder/editor/context/page/1/translations',
    ...over,
  })

describe('decideLocaleSwitch', () => {
  it('is a no-op for the current locale', () => {
    expect(decideLocaleSwitch(opt({ locale: 'en' }), 'en', false)).toBe('noop')
  })

  it('is a no-op when the option has no editor url', () => {
    expect(decideLocaleSwitch(opt({ editor_url: null }), 'en', true)).toBe('noop')
  })

  it('navigates directly when clean and different', () => {
    expect(decideLocaleSwitch(opt(), 'en', false)).toBe('navigate')
  })

  it('requires confirmation when dirty and different', () => {
    expect(decideLocaleSwitch(opt(), 'en', true)).toBe('confirm')
  })

  it('treats a creatable missing translation as a switch target', () => {
    expect(decideLocaleSwitch(creatable(), 'en', false)).toBe('navigate')
    expect(decideLocaleSwitch(creatable(), 'en', true)).toBe('confirm')
  })

  it('is a no-op for a missing translation the user cannot create', () => {
    expect(decideLocaleSwitch(creatable({ can_create: false, create_url: null }), 'en', false)).toBe('noop')
    expect(isOpenable(creatable({ can_create: false, create_url: null }))).toBe(false)
  })
})

describe('openLocaleTarget', () => {
  it('opens an existing translation directly without creating anything', async () => {
    const create = vi.fn()
    const navigate = vi.fn()

    await openLocaleTarget(opt(), { create, navigate })

    expect(create).not.toHaveBeenCalled()
    expect(navigate).toHaveBeenCalledWith('/pb/page/1/frontend?locale=vi')
  })

  it('creates a missing translation first, then opens the editor URL the server returns', async () => {
    const create = vi.fn(async () => '/pb/page/1/frontend?locale=vi')
    const navigate = vi.fn()

    await openLocaleTarget(creatable(), { create, navigate })

    expect(create).toHaveBeenCalledWith('/api/page-builder/editor/context/page/1/translations', 'vi')
    expect(navigate).toHaveBeenCalledWith('/pb/page/1/frontend?locale=vi')
  })

  it('rejects and stays put when creation fails', async () => {
    const navigate = vi.fn()
    const create = vi.fn(async () => {
      throw new Error('Could not provision the translated layout.')
    })

    await expect(openLocaleTarget(creatable(), { create, navigate })).rejects.toThrow('Could not provision')
    expect(navigate).not.toHaveBeenCalled()
  })

  it('rejects when the server returns no editor URL', async () => {
    const navigate = vi.fn()

    await expect(openLocaleTarget(creatable(), { create: async () => null, navigate })).rejects.toThrow()
    expect(navigate).not.toHaveBeenCalled()
  })

  it('never posts for a missing translation that is not creatable', async () => {
    const create = vi.fn()
    const navigate = vi.fn()

    await expect(
      openLocaleTarget(creatable({ can_create: false, create_url: null }), { create, navigate }),
    ).rejects.toThrow()
    expect(create).not.toHaveBeenCalled()
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('saveAndSwitch', () => {
  it('saves once, then navigates when the save clears dirty', async () => {
    const navigate = vi.fn()
    let dirty = true
    const save = vi.fn(async () => {
      dirty = false
    })

    const navigated = await saveAndSwitch('/pb?locale=vi', { save, stillDirty: () => dirty, navigate })

    expect(save).toHaveBeenCalledOnce()
    expect(navigated).toBe(true)
    expect(navigate).toHaveBeenCalledWith('/pb?locale=vi')
  })

  it('does NOT navigate when the save fails (still dirty)', async () => {
    const navigate = vi.fn()
    const save = vi.fn(async () => {
      /* save aborted — editor stays dirty */
    })

    const navigated = await saveAndSwitch('/pb?locale=vi', { save, stillDirty: () => true, navigate })

    expect(save).toHaveBeenCalledOnce()
    expect(navigated).toBe(false)
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('saveAndSwitch with an open step', () => {
  it('runs the open step only after a successful save', async () => {
    const order: string[] = []
    let dirty = true
    const switched = await saveAndSwitch(
      async () => {
        order.push('open')
      },
      {
        save: async () => {
          order.push('save')
          dirty = false
        },
        stillDirty: () => dirty,
      },
    )

    expect(switched).toBe(true)
    expect(order).toEqual(['save', 'open'])
  })

  it('never runs the open step after a failed save', async () => {
    const open = vi.fn(async () => {})
    const switched = await saveAndSwitch(open, { save: async () => {}, stillDirty: () => true })

    expect(switched).toBe(false)
    expect(open).not.toHaveBeenCalled()
  })
})

describe('switchWithoutSaving', () => {
  it('navigates without saving', () => {
    const navigate = vi.fn()
    switchWithoutSaving('/pb?locale=vi', navigate)
    expect(navigate).toHaveBeenCalledWith('/pb?locale=vi')
  })
})
