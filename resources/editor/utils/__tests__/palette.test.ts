import { describe, it, expect } from 'vitest'
import { reactive } from 'vue'
import { createComponentPayload } from '../palette'
import type { DefinitionMeta } from '../../types'

const heroDef = { type: 'hero', label: 'Hero', defaults: { heading: 'Hi', align: 'center' } } as unknown as DefinitionMeta
const buttonDef = { type: 'button', label: 'Button', defaults: { text: 'Click' } } as unknown as DefinitionMeta
const bareDef = { type: 'spacer', label: 'Spacer' } as unknown as DefinitionMeta

describe('createComponentPayload', () => {
  it('builds a section payload with defaults, empty blocks, and no id', () => {
    const payload = createComponentPayload(heroDef, 'section')
    expect(payload).toEqual({ type: 'hero', data: { heading: 'Hi', align: 'center' }, blocks: [] })
    expect('id' in payload).toBe(false)
  })

  it('builds a block payload with defaults and no blocks array', () => {
    const payload = createComponentPayload(buttonDef, 'block')
    expect(payload).toEqual({ type: 'button', data: { text: 'Click' } })
    expect('blocks' in payload).toBe(false)
  })

  it('falls back to empty data when a definition has no defaults', () => {
    expect(createComponentPayload(bareDef, 'block')).toEqual({ type: 'spacer', data: {} })
  })

  it('clones defaults so mutating the payload never touches the definition', () => {
    const payload = createComponentPayload(heroDef, 'section')
    ;(payload.data as Record<string, unknown>).heading = 'changed'
    expect(heroDef.defaults).toMatchObject({ heading: 'Hi' })
  })

  it('accepts a REACTIVE definition/defaults and returns a plain JSON object', () => {
    // Reproduces the browser bug source: metadata defaults are Vue reactive
    // proxies. structuredClone would throw on these — createComponentPayload
    // must not use it. Even with structuredClone forced to throw, this succeeds.
    const original = globalThis.structuredClone
    // Simulate the browser's DataCloneError on reactive proxies.
    globalThis.structuredClone = (() => {
      throw new Error('could not be cloned')
    }) as typeof structuredClone
    try {
      const reactiveDef = reactive({ type: 'hero', defaults: { heading: 'Hi', nested: { a: 1 } } }) as unknown as DefinitionMeta
      const payload = createComponentPayload(reactiveDef, 'section')
      expect(() => JSON.stringify(payload)).not.toThrow()
      expect(payload).toEqual({ type: 'hero', data: { heading: 'Hi', nested: { a: 1 } }, blocks: [] })
    } finally {
      globalThis.structuredClone = original
    }
  })
})
