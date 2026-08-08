import { describe, it, expect } from 'vitest'
import { getCaret, setCaret, placeCaretAtEnd, preserveCaret, autoResize } from '../CaretManager'

function input(value: string): HTMLInputElement {
  const el = document.createElement('input')
  el.type = 'text'
  el.value = value
  document.body.appendChild(el)
  return el
}

describe('CaretManager', () => {
  it('gets and sets the caret, clamped to the value length', () => {
    const el = input('hello')
    setCaret(el, 2)
    expect(getCaret(el)).toEqual([2, 2])

    setCaret(el, 99, 99)
    expect(getCaret(el)).toEqual([5, 5])
  })

  it('places the caret at the end', () => {
    const el = input('abcd')
    placeCaretAtEnd(el)
    expect(getCaret(el)).toEqual([4, 4])
  })

  it('preserves the caret across a value mutation', () => {
    const el = input('hello')
    setCaret(el, 3)
    preserveCaret(el, () => {
      el.value = 'help!'
    })
    expect(getCaret(el)).toEqual([3, 3])
  })

  it('auto-resizes a textarea without an inner scrollbar', () => {
    const el = document.createElement('textarea')
    el.value = 'a\nb\nc'
    document.body.appendChild(el)
    const h = autoResize(el)
    expect(el.style.overflowY).toBe('hidden')
    expect(el.style.height.endsWith('px')).toBe(true)
    expect(typeof h).toBe('number')
  })
})
