import { describe, it, expect } from 'vitest'
import { BoxModelSession } from '../BoxModelSession'

describe('BoxModelSession', () => {
  it('derives the value from the drag delta, clamped and rounded', () => {
    const s = new BoxModelSession('c1', 'padding', 'top', 20)
    expect(s.valueAt(0, -4.6)).toBe(25) // 20 + 4.6 -> round 25
    expect(s.valueAt(0, 100)).toBe(0) // dragging inward past zero clamps
  })

  it('snaps to a 10px grid when requested', () => {
    const s = new BoxModelSession('c1', 'margin', 'bottom', 12)
    expect(s.valueAt(0, 6, true)).toBe(20) // 18 -> snap 20
  })

  it('exposes the camelCase css property + style patch', () => {
    expect(new BoxModelSession('c1', 'padding', 'left', 0).styleProp).toBe('paddingLeft')
    expect(new BoxModelSession('c1', 'margin', 'top', 0).stylePatch(15)).toEqual({ marginTop: '15px' })
  })

  it('folds a value into spacing data, preserving other sides + the other box', () => {
    const s = new BoxModelSession('c1', 'padding', 'top', 0)
    const next = s.spacingUpdate({ padding: { left: 8 }, margin: { bottom: 4 } }, 24)
    expect(next).toEqual({ padding: { left: 8, top: 24 }, margin: { bottom: 4 } })
  })

  it('seeds empty boxes when the component had no spacing', () => {
    const s = new BoxModelSession('c1', 'margin', 'right', 0)
    expect(s.spacingUpdate(undefined, 10)).toEqual({ padding: {}, margin: { right: 10 } })
  })
})
