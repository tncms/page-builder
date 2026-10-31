// PB-TEXT-AUTHORING-R1 — the visual Text editor's client-side HTML vocabulary.
//
// Deterministic, dependency-free normalization of what a contenteditable produces
// (or what is pasted) into the Text block's vocabulary, so the value the editor
// emits is stable and small. It mirrors Page Builder's server narrowing
// (`Support\AuthoredHtml`, TEXT profile) but is NOT a security boundary: every
// render goes through Core's `cms_html()` and that narrowing on the server.
//
// Parsing goes through a <template> element, whose content is an inert fragment that
// is never connected to the document, so pasted scripts never run.

const KEEP = new Set(['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'a', 'blockquote', 'code', 'pre'])
const ALIAS: Record<string, string> = { b: 'strong', i: 'em', strike: 's', del: 's', h1: 'p', h2: 'p', h3: 'p', h4: 'p', h5: 'p', h6: 'p' }
const DROP = new Set([
  'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'select', 'option',
  'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'head', 'title', 'img', 'video', 'audio',
  'canvas', 'frame', 'frameset', 'applet',
])
const BLOCK = new Set(['p', 'div', 'ul', 'ol', 'li', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'section', 'article', 'header', 'footer'])
const SAFE_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:']

/** Whether a link address is one the editor accepts (same rule family as the server). */
export function isSafeLinkUrl(url: string): boolean {
  // Strip control characters / whitespace first so "java\tscript:" cannot slip through.
  // eslint-disable-next-line no-control-regex
  const value = url.replace(/[\u0000- \u007f-\u009f]/g, '')
  if (value === '') return false
  // Relative, root-relative and protocol-relative addresses (Core's HtmlSanitizer allows the same).
  if (value.startsWith('/')) return true
  if (value.startsWith('#') || value.startsWith('?')) return true
  const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(value)
  if (!scheme) return !value.includes(':')
  return SAFE_SCHEMES.includes(scheme[1].toLowerCase() + ':')
}

function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

/** A legacy plain Text value as the equivalent rich HTML (display only; never emitted on open). */
export function plainToRichHtml(text: string): string {
  const value = text.replace(/\r\n?/g, '\n').trim()
  if (value === '') return ''
  return value
    .split(/\n{2,}/)
    .map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
    .join('')
}

function parse(html: string): DocumentFragment {
  const template = document.createElement('template')
  template.innerHTML = html
  return template.content
}

function hasBlockChild(el: Element): boolean {
  return Array.from(el.children).some((child) => BLOCK.has(child.tagName.toLowerCase()))
}

function serializeChildren(node: Node): string {
  let out = ''
  node.childNodes.forEach((child) => {
    out += serializeNode(child)
  })
  return out
}

function serializeNode(node: Node): string {
  if (node.nodeType === Node.TEXT_NODE) return escapeHtml(node.textContent ?? '').replace(/ /g, '&nbsp;')
  if (node.nodeType !== Node.ELEMENT_NODE) return ''

  const el = node as Element
  let tag = el.tagName.toLowerCase()
  if (DROP.has(tag)) return ''
  if (tag === 'div') tag = hasBlockChild(el) ? '' : 'p'
  tag = ALIAS[tag] ?? tag
  // A Text block never introduces a heading rank; inline styling spans etc. unwrap.
  if (!KEEP.has(tag)) return serializeChildren(el)
  if (tag === 'br') return '<br>'

  let attrs = ''
  if (tag === 'a') {
    const href = (el.getAttribute('href') ?? '').trim()
    if (href !== '' && isSafeLinkUrl(href)) attrs += ` href="${escapeHtml(href)}"`
    if ((el.getAttribute('target') ?? '').trim().toLowerCase() === '_blank') attrs += ' target="_blank" rel="noopener noreferrer"'
  }

  const inner = serializeChildren(el)
  // Drop empty inline wrappers left behind by editing (e.g. <strong></strong>).
  if (inner === '' && tag !== 'p' && tag !== 'li') return ''
  return `<${tag}${attrs}>${inner}</${tag}>`
}

/**
 * Normalize editor/pasted HTML into the Text vocabulary. Top-level inline runs are
 * wrapped in one paragraph each; empty paragraphs are dropped, so an editor that
 * holds only an empty paragraph or a lone line break normalizes to ''.
 */
export function normalizeRichHtml(html: string): string {
  const body = parse(html)
  let out = ''
  let inline = ''
  const flush = () => {
    if (inline.replace(/<br>/g, '').replace(/&nbsp;/g, ' ').trim() !== '') out += `<p>${inline}</p>`
    inline = ''
  }

  body.childNodes.forEach((child) => {
    const serialized = serializeNode(child)
    if (/^<(p|ul|ol|blockquote|pre)[ >]/.test(serialized)) {
      flush()
      out += serialized
    } else {
      inline += serialized
    }
  })
  flush()

  // Empty paragraphs (a bare Enter) carry nothing and are not stored.
  return out.replace(/<p>(<br>|&nbsp;|\s)*<\/p>/g, '')
}

/** UTF-8 byte length (the server bound is in bytes). */
export function byteLength(value: string): number {
  return new TextEncoder().encode(value).length
}
