import { describe, it, expect, vi } from 'vitest'
import {
  decideLocaleSwitch,
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

describe('switchWithoutSaving', () => {
  it('navigates without saving', () => {
    const navigate = vi.fn()
    switchWithoutSaving('/pb?locale=vi', navigate)
    expect(navigate).toHaveBeenCalledWith('/pb?locale=vi')
  })
})
