import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { deviceWidth, isDevice, DEVICE_WIDTHS } from '../device'
import { useShellStore } from '../shellStore'

describe('device presets', () => {
  it('maps each device to its width', () => {
    expect(deviceWidth('desktop')).toBe('100%')
    expect(deviceWidth('tablet')).toBe('768px')
    expect(deviceWidth('mobile')).toBe('390px')
    expect(Object.keys(DEVICE_WIDTHS)).toEqual(['desktop', 'tablet', 'mobile'])
  })

  it('validates device identifiers', () => {
    expect(isDevice('tablet')).toBe(true)
    expect(isDevice('watch')).toBe(false)
    expect(isDevice(null)).toBe(false)
  })
})

describe('shell store device state', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('changing device updates the iframe width', () => {
    const shell = useShellStore()
    expect(shell.device).toBe('desktop')
    expect(shell.iframeWidth).toBe('100%')

    shell.setDevice('mobile')
    expect(shell.device).toBe('mobile')
    expect(shell.iframeWidth).toBe('390px')

    shell.setDevice('tablet')
    expect(shell.iframeWidth).toBe('768px')
  })

  it('ignores an invalid device', () => {
    const shell = useShellStore()
    // @ts-expect-error — deliberately invalid at the call site
    shell.setDevice('foldable')
    expect(shell.device).toBe('desktop')
  })
})
