import { describe, it, expect, beforeEach } from 'vitest'
import { inlineFieldsFor, isInlineEditable, locateField } from '../TextLocator'

function ctaMarker(): HTMLElement {
  document.body.innerHTML = `
    <div class="pb-editor-render-marker" data-pb-component-id="cta-1" data-pb-component-type="cta">
      <section class="pb-cta">
        <h2 class="pb-cta__title"><span id="inner">Hi</span></h2>
        <p class="pb-cta__text">Body</p>
        <a class="pb-cta__button" href="/x">Go</a>
      </section>
    </div>`
  return document.querySelector('.pb-editor-render-marker') as HTMLElement
}

describe('TextLocator', () => {
  it('lists inline-editable fields for known types', () => {
    expect(inlineFieldsFor('cta').map((f) => f.field)).toEqual(['title', 'text', 'button_label'])
    expect(inlineFieldsFor('text-section')[0]).toMatchObject({ field: 'content', multiline: true })
    expect(isInlineEditable('cta')).toBe(true)
    expect(isInlineEditable('unknown')).toBe(false)
    expect(inlineFieldsFor('unknown')).toEqual([])
  })

  let marker: HTMLElement
  beforeEach(() => {
    marker = ctaMarker()
  })

  it('locates the field for a direct hit', () => {
    const title = marker.querySelector('.pb-cta__title') as HTMLElement
    const hit = locateField('cta', marker, title)
    expect(hit?.field.field).toBe('title')
    expect(hit?.el).toBe(title)
  })

  it('locates the field for a nested target (contains)', () => {
    const inner = marker.querySelector('#inner') as HTMLElement
    expect(locateField('cta', marker, inner)?.field.field).toBe('title')
  })

  it('reports multiline for the text field and single-line for the button', () => {
    const text = marker.querySelector('.pb-cta__text') as HTMLElement
    const button = marker.querySelector('.pb-cta__button') as HTMLElement
    expect(locateField('cta', marker, text)?.field.multiline).toBe(true)
    expect(locateField('cta', marker, button)?.field.multiline).toBe(false)
  })

  it('returns null for a non-editable target (section padding)', () => {
    const section = marker.querySelector('.pb-cta') as HTMLElement
    expect(locateField('cta', marker, section)).toBeNull()
  })
})
