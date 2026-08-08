import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PatternVariablesDialog from '../PatternVariablesDialog.vue'
import type { PatternManifest, PatternVariable } from '../types'

function variable(over: Partial<PatternVariable>): PatternVariable {
  return { id: 'x', type: 'text', label: 'X', default: '', required: false, options: [], ...over }
}

function manifest(variables: PatternVariable[]): PatternManifest {
  return {
    id: 'hero.startup', name: 'Startup Hero', slug: 'startup-hero', category: 'hero', description: '',
    tags: [], version: '1.0.0', author: '', thumbnail: 'p.svg', thumbnail_url: '/t.svg', keywords: [],
    difficulty: 'beginner', featured: false, premium: false, previewWidth: 1280, previewHeight: 720,
    variables,
  }
}

describe('PatternVariablesDialog — manifest-driven fields', () => {
  it('renders one field per declared variable (no hardcoded fields)', () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: {
        pattern: manifest([
          variable({ id: 'title', type: 'text', label: 'Headline' }),
          variable({ id: 'body', type: 'textarea', label: 'Body' }),
          variable({ id: 'go', type: 'toggle', label: 'Go', default: true }),
        ]),
        inserting: false,
      },
    })

    expect(wrapper.find('#pb-var-title').exists()).toBe(true)
    expect(wrapper.find('textarea#pb-var-body').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"]#pb-var-go').exists()).toBe(true)
  })

  it('emits insert with resolved values (defaults + edits), never touching BuilderState', async () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: {
        pattern: manifest([
          variable({ id: 'title', default: 'Default Title' }),
          variable({ id: 'button', default: 'Go' }),
        ]),
        inserting: false,
      },
    })

    await wrapper.find('#pb-var-title').setValue('Edited Title')
    await wrapper.findAll('button').find((b) => b.text() === 'Insert')!.trigger('click')

    const events = wrapper.emitted('insert')
    expect(events).toHaveLength(1)
    expect(events![0][0]).toEqual({ title: 'Edited Title', button: 'Go' })
  })

  it('blocks insert and shows an error when a required field is empty', async () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: { pattern: manifest([variable({ id: 'title', required: true, default: '' })]), inserting: false },
    })

    await wrapper.findAll('button').find((b) => b.text() === 'Insert')!.trigger('click')

    expect(wrapper.emitted('insert')).toBeUndefined()
    expect(wrapper.find('.pb-pattern-field__error').exists()).toBe(true)
  })

  it('updates the live preview panel as values change', async () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: { pattern: manifest([variable({ id: 'title', label: 'Headline', default: 'Old' })]), inserting: false },
    })

    expect(wrapper.find('.pb-pattern-vars__preview').text()).toContain('Old')
    await wrapper.find('#pb-var-title').setValue('New Live Value')
    expect(wrapper.find('.pb-pattern-vars__preview').text()).toContain('New Live Value')
  })

  it('emits close from Cancel', async () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: { pattern: manifest([variable({ id: 'title' })]), inserting: false },
    })

    await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  // Phase 26.39 RC a11y hardening — Escape must dismiss the dialog (was missing).
  it('emits close on Escape', async () => {
    const wrapper = mount(PatternVariablesDialog, {
      props: { pattern: manifest([variable({ id: 'title' })]), inserting: false },
    })

    await wrapper.find('.pb-pattern-dialog__box').trigger('keydown.esc')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
