import { describe, it, expect } from 'vitest'
import {
  initialVariableValues,
  resolveVariableValues,
  validateVariableValues,
  variableDefault,
} from '../resolveVariables'
import type { PatternVariable } from '../types'

function variable(over: Partial<PatternVariable>): PatternVariable {
  return { id: 'x', type: 'text', label: 'X', default: '', required: false, options: [], ...over }
}

describe('resolveVariables — defaults', () => {
  it('uses declared default, else a type-appropriate empty', () => {
    expect(variableDefault(variable({ default: 'Hi' }))).toBe('Hi')
    expect(variableDefault(variable({ type: 'toggle', default: null as unknown as boolean }))).toBe(false)
    expect(variableDefault(variable({ type: 'number', default: null as unknown as number }))).toBe(0)
  })

  it('seeds an initial value map from defaults', () => {
    const vars = [variable({ id: 'a', default: 'A' }), variable({ id: 'b', type: 'toggle', default: true })]
    expect(initialVariableValues(vars)).toEqual({ a: 'A', b: true })
  })
})

describe('resolveVariables — merge', () => {
  const vars = [variable({ id: 'title', default: 'Default' }), variable({ id: 'sub', default: 'Sub' })]

  it('merges custom values over defaults', () => {
    expect(resolveVariableValues(vars, { title: 'Custom' })).toEqual({ title: 'Custom', sub: 'Sub' })
  })

  it('falls back to defaults for missing/null values', () => {
    expect(resolveVariableValues(vars, { title: null })).toEqual({ title: 'Default', sub: 'Sub' })
    expect(resolveVariableValues(vars, {})).toEqual({ title: 'Default', sub: 'Sub' })
  })
})

describe('resolveVariables — validation', () => {
  it('flags required fields that are empty', () => {
    const vars = [variable({ id: 'title', required: true })]
    expect(validateVariableValues(vars, { title: '' })).toHaveProperty('title')
    expect(validateVariableValues(vars, { title: 'ok' })).toEqual({})
  })

  it('flags non-numeric values for number fields', () => {
    const vars = [variable({ id: 'n', type: 'number' })]
    expect(validateVariableValues(vars, { n: 'abc' })).toHaveProperty('n')
    expect(validateVariableValues(vars, { n: '42' })).toEqual({})
  })

  it('treats toggles as always valid', () => {
    const vars = [variable({ id: 't', type: 'toggle', required: true })]
    expect(validateVariableValues(vars, { t: false })).toEqual({})
  })
})
