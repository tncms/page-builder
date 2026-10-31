// PB-TEXT-AUTHORING-R1 — the visual Text editor (`richtext`), its client vocabulary,
// and the Custom HTML source editor (`htmlsource`). Mounted with the schema shape
// PageBuilderManager::editorMetadata() publishes and a FieldController stub, and
// asserted on what each editor emits (and, crucially, when it emits nothing).

import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

import { resolveFieldEditor } from '../fieldEditorRegistry'
import { FieldControllerKey, type FieldController } from '../fieldController'
import type { SchemaField } from '../schema'
import '../registerBuiltinFieldEditors'
import RichTextField from '../fields/RichTextField.vue'
import HtmlSourceField from '../fields/HtmlSourceField.vue'
import { isSafeLinkUrl, normalizeRichHtml, plainToRichHtml } from '../richText/richTextHtml'

const STRINGS = { toolbar: 'Định dạng văn bản', editor: 'Trình soạn thảo văn bản', bold: 'In đậm', tooLarge: 'Quá lớn' }

function richField(maxBytes = 65536): SchemaField {
  return { name: 'content', type: 'richtext', label: 'Text', translatable: true, editor: { legacyField: 'text', maxBytes, strings: STRINGS } }
}

/** A controller over a node's raw data in one editing locale (what PropertyInspector provides). */
function controller(data: Record<string, unknown>, locale = 'vi'): FieldController {
  const read = (field: SchemaField): unknown => {
    const raw = data[field.name]
    if (!field.translatable) return raw
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) return (raw as Record<string, unknown>)[locale] ?? ''
    return typeof raw === 'string' ? raw : ''
  }
  return {
    getValue: read,
    setValue: () => {},
    commit: () => {},
    revert: () => {},
    locale,
    fieldDirty: () => false,
    revertField: () => {},
    dirtyCount: () => 0,
  }
}

function mountRich(data: Record<string, unknown>, locale = 'vi', maxBytes = 65536) {
  const ctrl = controller(data, locale)
  return mount(RichTextField, {
    props: { modelValue: ctrl.getValue(richField()), field: richField(maxBytes) },
    global: { provide: { [FieldControllerKey as symbol]: ctrl } },
    attachTo: document.body,
  })
}

function surface(wrapper: ReturnType<typeof mount>): HTMLElement {
  return wrapper.find('[contenteditable="true"]').element as HTMLElement
}

describe('richTextHtml vocabulary', () => {
  it('keeps the Text vocabulary and canonicalizes aliases', () => {
    expect(normalizeRichHtml('<b>b</b> <i>i</i> <u>u</u> <strike>s</strike>')).toBe('<p><strong>b</strong> <em>i</em> <u>u</u> <s>s</s></p>')
    expect(normalizeRichHtml('<h1>Big</h1><ul><li>a</li></ul><blockquote>q</blockquote><pre><code>x</code></pre>')).toBe(
      '<p>Big</p><ul><li>a</li></ul><blockquote>q</blockquote><pre><code>x</code></pre>',
    )
  })

  it('turns browser line wrappers into paragraphs and drops empty ones', () => {
    expect(normalizeRichHtml('first<div>second</div><div><br></div>')).toBe('<p>first</p><p>second</p>')
    expect(normalizeRichHtml('<p><br></p>')).toBe('')
    expect(normalizeRichHtml('<div><br></div><p>&nbsp;</p>')).toBe('')
  })

  it('removes executable and foreign content from pasted HTML', () => {
    const out = normalizeRichHtml(
      '<meta charset="utf-8"><style>p{}</style><p onclick="x()" style="color:red" class="c">ok<script>alert(1)</script></p>'
        + '<iframe src="https://evil.test"></iframe><img src=x onerror=alert(1)><svg><foreignObject>f</foreignObject></svg>'
        + '<form><input value="p"></form><a href="javascript:alert(1)">bad</a><a href="https://e.test" target="_blank">ext</a>',
    )
    expect(out).toBe('<p>ok</p><p><a>bad</a><a href="https://e.test" target="_blank" rel="noopener noreferrer">ext</a></p>')
  })

  it('accepts only safe link addresses', () => {
    for (const ok of ['https://a.test', 'http://a.test', 'mailto:a@b.test', 'tel:+84', '/path', '//cdn.test/x', '#top', '?q=1', 'page.html']) {
      expect(isSafeLinkUrl(ok), ok).toBe(true)
    }
    for (const bad of ['javascript:alert(1)', ' JavaScript:x', 'jav\tascript:x', 'data:text/html,x', 'vbscript:x', '']) {
      expect(isSafeLinkUrl(bad), bad).toBe(false)
    }
  })

  it('shows legacy plain text as escaped paragraphs', () => {
    expect(plainToRichHtml('a < b\nc\n\n\nd')).toBe('<p>a &lt; b<br>c</p><p>d</p>')
    expect(plainToRichHtml('  ')).toBe('')
  })
})

describe('RichTextField', () => {
  it('is registered for the richtext type and needs the field', () => {
    const editor = resolveFieldEditor('richtext')
    expect(editor?.component).toBe(RichTextField)
    expect(editor?.needsField).toBe(true)
  })

  it('opens a legacy Text node without writing anything', () => {
    const wrapper = mountRich({ text: { vi: 'Xin <chào>\nbạn', en: 'Hello' } })
    expect(surface(wrapper).innerHTML).toBe('<p>Xin &lt;chào&gt;<br>bạn</p>')
    expect(wrapper.emitted('update')).toBeUndefined()
    expect(wrapper.emitted('commit')).toBeUndefined()
    wrapper.unmount()
  })

  it('opens the locale’s own rich value, and an explicit empty value stays empty', () => {
    const rich = mountRich({ text: 'Old', content: { vi: '<p><strong>Mới</strong></p>' } })
    expect(surface(rich).innerHTML).toBe('<p><strong>Mới</strong></p>')
    rich.unmount()

    const cleared = mountRich({ text: 'Old', content: { vi: '' } })
    expect(surface(cleared).innerHTML).toBe('')
    cleared.unmount()

    const otherLocale = mountRich({ text: { vi: 'Bản cũ', en: 'Old english' }, content: { vi: '<p>Mới</p>' } }, 'en')
    expect(surface(otherLocale).innerHTML).toBe('<p>Old english</p>')
    otherLocale.unmount()
  })

  it('a genuine edit emits normalized HTML once, and commits on blur', async () => {
    const wrapper = mountRich({ text: 'Old' })
    const el = surface(wrapper)
    el.innerHTML = '<p>New <b>bold</b></p><div>line</div>'
    await wrapper.find('[contenteditable="true"]').trigger('input')
    await wrapper.find('[contenteditable="true"]').trigger('input')
    expect(wrapper.emitted('update')).toEqual([['<p>New <strong>bold</strong></p><p>line</p>']])

    await wrapper.find('.pb-richtext').trigger('focusout', { relatedTarget: null })
    expect(wrapper.emitted('commit')).toHaveLength(1)
    wrapper.unmount()
  })

  it('blurring without an edit commits nothing', async () => {
    const wrapper = mountRich({ text: 'Old' })
    await wrapper.find('.pb-richtext').trigger('focusout', { relatedTarget: null })
    expect(wrapper.emitted('commit')).toBeUndefined()
    wrapper.unmount()
  })

  it('over the byte bound shows a bounded error and does not stage the value', async () => {
    const wrapper = mountRich({ text: 'Old' }, 'vi', 20)
    surface(wrapper).innerHTML = `<p>${'x'.repeat(40)}</p>`
    await wrapper.find('[contenteditable="true"]').trigger('input')
    expect(wrapper.emitted('update')).toBeUndefined()
    expect(wrapper.find('[role="alert"]').text()).toBe('Quá lớn')
    expect(wrapper.find('[contenteditable="true"]').attributes('aria-invalid')).toBe('true')
    wrapper.unmount()
  })

  it('exposes an accessible, localized toolbar and textbox', () => {
    const wrapper = mountRich({ text: 'Old' })
    const toolbar = wrapper.find('[role="toolbar"]')
    expect(toolbar.attributes('aria-label')).toBe('Định dạng văn bản')
    const tools = toolbar.findAll('button').map((b) => b.attributes('data-tool'))
    expect(tools).toEqual(['bold', 'italic', 'underline', 'bulleted', 'numbered', 'quote', 'code', 'codeBlock', 'link', 'clear', 'undo', 'redo'])
    expect(toolbar.find('[data-tool="bold"]').attributes('aria-label')).toBe('In đậm')
    expect(toolbar.find('[data-tool="bold"]').attributes('aria-pressed')).toBe('false')
    for (const button of toolbar.findAll('button')) expect(button.attributes('type')).toBe('button')
    const box = wrapper.find('[role="textbox"]')
    expect(box.attributes('aria-multiline')).toBe('true')
    expect(box.attributes('aria-label')).toBe('Trình soạn thảo văn bản')
    wrapper.unmount()
  })

  it('refuses an unsafe link address with a bounded message', async () => {
    const wrapper = mountRich({ text: 'Old' })
    await wrapper.find('[data-tool="link"]').trigger('click')
    await wrapper.find('[data-link-url]').setValue('javascript:alert(1)')
    await wrapper.find('[data-link-apply]').trigger('click')
    expect(wrapper.find('[data-link-url]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.pb-richtext__link [role="alert"]').exists()).toBe(true)
    expect(wrapper.emitted('update')).toBeUndefined()
    wrapper.unmount()
  })

  it('linking a formatted selection keeps its formatting (browser finding)', async () => {
    const calls: Array<[string, string | undefined]> = []
    const original = document.execCommand
    document.execCommand = ((command: string, _ui?: boolean, value?: string) => {
      calls.push([command, value])
      if (command === 'createLink') {
        // What a browser does: wrap the selected nodes (formatting kept) in an anchor.
        const em = document.querySelector('.pb-richtext__surface em') as HTMLElement
        const a = document.createElement('a')
        a.setAttribute('href', value ?? '')
        em.replaceWith(a)
        a.appendChild(em)
      }
      return true
    }) as typeof document.execCommand
    try {
      const wrapper = mountRich({ text: 'Old', content: { vi: '<p>Read <em>this</em></p>' } }, 'vi')
      document.body.appendChild(wrapper.element)
      const range = document.createRange()
      range.selectNodeContents(surface(wrapper).querySelector('em') as Node)
      window.getSelection()?.removeAllRanges()
      window.getSelection()?.addRange(range)
      await wrapper.find('[data-tool="link"]').trigger('click')
      await wrapper.find('[data-link-url]').setValue('https://example.org/x')
      await wrapper.find('.pb-richtext__link-tab input').setValue(true)
      await wrapper.find('[data-link-apply]').trigger('click')
      expect(calls.map(([c]) => c)).toContain('createLink')
      expect(calls.some(([c]) => c === 'insertHTML')).toBe(false)
      const emitted = wrapper.emitted('update') as string[][]
      expect(emitted.at(-1)?.[0]).toBe('<p>Read <a href="https://example.org/x" target="_blank" rel="noopener noreferrer"><em>this</em></a></p>')
      wrapper.unmount()
    } finally {
      document.execCommand = original
    }
  })

  it('pasted HTML is normalized before it is inserted', async () => {
    const inserted: string[] = []
    const original = document.execCommand
    document.execCommand = ((command: string, _ui?: boolean, value?: string) => {
      if (command === 'insertHTML') inserted.push(value ?? '')
      return true
    }) as typeof document.execCommand
    try {
      const wrapper = mountRich({ text: 'Old' })
      const event = new Event('paste', { bubbles: true, cancelable: true }) as ClipboardEvent
      Object.defineProperty(event, 'clipboardData', {
        value: { getData: (type: string) => (type === 'text/html' ? '<p onclick="x()">Hi<script>alert(1)</script></p>' : 'Hi') },
      })
      surface(wrapper).dispatchEvent(event)
      expect(event.defaultPrevented).toBe(true)
      expect(inserted).toEqual(['<p>Hi</p>'])
      wrapper.unmount()
    } finally {
      document.execCommand = original
    }
  })
})

describe('HtmlSourceField', () => {
  function htmlField(maxBytes = 65536): SchemaField {
    return {
      name: 'html',
      type: 'htmlsource',
      label: 'HTML source',
      translatable: true,
      editor: { maxBytes, maxDepth: 3, strings: { htmlSource: 'Mã nguồn HTML', htmlHelp: 'Được làm sạch', htmlEmpty: 'Chưa có HTML', tooLarge: 'Quá lớn' } },
    }
  }

  it('is registered for the htmlsource type and needs the field', () => {
    const editor = resolveFieldEditor('htmlsource')
    expect(editor?.component).toBe(HtmlSourceField)
    expect(editor?.needsField).toBe(true)
  })

  it('edits plain source verbatim, labelled and described, and commits on blur', async () => {
    const wrapper = mount(HtmlSourceField, { props: { modelValue: '', field: htmlField() } })
    const input = wrapper.find('textarea')
    expect(input.attributes('aria-label')).toBe('Mã nguồn HTML')
    expect(input.attributes('placeholder')).toBe('Chưa có HTML')
    expect(input.attributes('spellcheck')).toBe('false')
    expect(wrapper.find(`#${input.attributes('aria-describedby')}`).text()).toBe('Được làm sạch')

    const source = '<p onclick="x()">Hi <script>alert(1)</script></p>'
    await input.setValue(source)
    expect(wrapper.emitted('update')).toEqual([[source]])
    await input.trigger('blur')
    expect(wrapper.emitted('commit')).toHaveLength(1)
  })

  it('does not stage source over the byte or nesting bound', async () => {
    const wrapper = mount(HtmlSourceField, { props: { modelValue: '', field: htmlField(10) } })
    await wrapper.find('textarea').setValue('<p>far too long</p>')
    expect(wrapper.emitted('update')).toBeUndefined()
    expect(wrapper.find('[role="alert"]').text()).toBe('Quá lớn')

    const deep = mount(HtmlSourceField, { props: { modelValue: '', field: htmlField() } })
    await deep.find('textarea').setValue('<div><div><div><div>x</div></div></div></div>')
    expect(deep.emitted('update')).toBeUndefined()
    await deep.find('textarea').setValue('<div><div><div><br><img src="/a.png">x</div></div></div>')
    expect(deep.emitted('update')).toHaveLength(1)
  })
})
