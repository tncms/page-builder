import { describe, it, expect } from 'vitest'
import { isExternalComponentType } from '../unavailableNodes'
import { validateLayout } from '../layoutValidation'

// PB-XREG-4-R1 — the client mirrors the server's ComponentIdentifier grammar so only a
// well-formed namespaced type can count as "unavailable"; anything else stays invalid.
describe('isExternalComponentType', () => {
  it.each(['a:b', 'travel-booking:tour-grid', 'pbx4-aurora:badge', 'x1:y-2'])('accepts %s', (type) => {
    expect(isExternalComponentType(type)).toBe(true)
  })

  it.each(['', 'heading', 'A:b', 'a:b:c', 'a:', ':b', 'a-:b', 'a:b_c', `${'a'.repeat(65)}:b`, 42, null])('rejects %s', (type) => {
    expect(isExternalComponentType(type)).toBe(false)
  })
})

describe('validateLayout with unavailable components', () => {
  const metadata = { sections: [{ type: 'hero' }], blocks: [{ type: 'heading' }] } as never

  it('does not flag a namespaced type missing from metadata', () => {
    const issues = validateLayout({ schema: 'v1', sections: [{ id: 's', type: 'acme:grid', data: {}, blocks: [{ id: 'b', type: 'acme:card', data: {} }] }] } as never, metadata)
    expect(issues).toEqual([])
  })

  it('still flags a bare unknown type', () => {
    const issues = validateLayout({ schema: 'v1', sections: [{ id: 's', type: 'ghost', data: {}, blocks: [] }] } as never, metadata)
    expect(issues.map((i) => i.code)).toEqual(['unknown-type'])
  })
})
