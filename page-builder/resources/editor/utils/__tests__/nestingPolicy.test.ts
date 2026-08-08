import { describe, it, expect } from 'vitest'
import { canContain, prefersInsideContainer } from '../nestingPolicy'

describe('nestingPolicy — composition nesting (mirrors PHP NestingPolicy)', () => {
  it('allows the canonical composition tree', () => {
    expect(canContain('container', 'column')).toBe(true)
    expect(canContain('column', 'heading')).toBe(true)
    expect(canContain('column', 'button')).toBe(true)
    expect(canContain('section', 'container')).toBe(true)
  })

  it('stays permissive for sections, legacy and unknown types', () => {
    expect(canContain('hero', 'text')).toBe(true)
    expect(canContain('section', 'image')).toBe(true)
    expect(canContain('some-plugin-block', 'anything')).toBe(true)
  })

  it('rejects nonsensical composition nesting', () => {
    expect(canContain('container', 'heading')).toBe(false) // Element → Container
    expect(canContain('container', 'container')).toBe(false) // Container → Container
    expect(canContain('column', 'column')).toBe(false) // Column → Column
    expect(canContain('column', 'container')).toBe(false) // Container → Column
  })
})

describe('prefersInsideContainer (Phase 26.3.7 — nearest valid parent)', () => {
  it('redirects an element into a container it fits (Text onto a Column edge → inside)', () => {
    expect(prefersInsideContainer('text', 'column')).toBe(true)
    expect(prefersInsideContainer('image', 'column')).toBe(true)
  })

  it('keeps columns/containers as siblings so they can be reordered', () => {
    expect(prefersInsideContainer('column', 'column')).toBe(false)
    expect(prefersInsideContainer('container', 'section')).toBe(false)
  })

  it('is false when the container would reject the element anyway (Column into a Container edge)', () => {
    expect(prefersInsideContainer('text', 'container')).toBe(false) // container only holds columns
  })
})
