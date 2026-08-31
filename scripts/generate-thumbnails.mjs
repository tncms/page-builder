// PB-FREE-LIBRARY-DESIGN-1-F — deterministic catalog thumbnail generator (DEV/GENERATION ONLY).
//
// Renders each of the 17 runtime-visible Free Library resources through the SAME canonical
// Runtime Preview endpoint the editor uses (page-builder/preview), captures a representative
// top crop at a frozen viewport, and writes an optimized WebP into the plugin's own public
// asset tree plus a hash manifest. It is NEVER required at plugin runtime or on shared hosting:
// the shipped WebP files work with no Node, Chromium, or Sharp.
//
// Determinism: frozen viewport + device-scale-factor, fonts-ready gate, fixed crop geometry,
// fixed WebP quality, stripped metadata. Fails closed on 404, console error, failed request,
// empty/placeholder render, or a missing rendered section.
//
// Usage: PB_BASE_URL=http://127.0.0.1:8100 PB_ADMIN_EMAIL=... PB_ADMIN_PASSWORD=... \
//        node scripts/generate-thumbnails.mjs

import { chromium } from 'playwright'
import sharp from 'sharp'
import { createHash } from 'node:crypto'
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const PLUGIN_ROOT = resolve(__dirname, '..')
const OUT_DIR = resolve(PLUGIN_ROOT, 'public/img/thumbnails')
const MANIFEST = resolve(PLUGIN_ROOT, 'resources/thumbnails/manifest.json')

const BASE_URL = process.env.PB_BASE_URL || 'http://127.0.0.1:8100'
const EMAIL = process.env.PB_ADMIN_EMAIL || ''
const PASSWORD = process.env.PB_ADMIN_PASSWORD || ''

// Frozen render + output geometry (the deterministic contract).
const VIEWPORT = { width: 1200, height: 900 }
const DSF = 1
const OUT = { width: 600, height: 450 } // 4:3 catalog card crop
const WEBP_QUALITY = 82

// The 17 runtime-visible resources (census re-freeze): 14 Resource Browser + 3 Template Picker.
const SECTIONS = ['hero-modern', 'hero-company', 'hero-saas', 'feature-grid', 'feature-cards', 'testimonials', 'logo-strip', 'cta-banner', 'pricing']
const PAGES = ['company-home', 'agency-home', 'saas-landing', 'portfolio', 'blog-home']
const TEMPLATES = ['hero-basic', 'feature-grid-basic', 'cta-basic']

const targets = [
  ...SECTIONS.map((id) => ({ key: `section-${id}`, source: 'rid', ref: `builtin:section:${id}` })),
  ...PAGES.map((id) => ({ key: `page-${id}`, source: 'rid', ref: `builtin:page:${id}` })),
  ...TEMPLATES.map((id) => ({ key: `template-${id}`, source: 'builtin-template', ref: id })),
]

const sha256 = (buf) => createHash('sha256').update(buf).digest('hex')

async function login(page) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle' })
  await page.fill('input[type="email"], #data\\.email, input[name="email"]', EMAIL)
  await page.fill('input[type="password"], input[name="password"]', PASSWORD)
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[type="submit"]')])
}

async function capture(page, target) {
  const url = `${BASE_URL}/page-builder/preview?source=${encodeURIComponent(target.source)}&ref=${encodeURIComponent(target.ref)}&device=desktop`
  const consoleErrors = []
  const failedRequests = []
  page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text()))
  page.on('requestfailed', (r) => failedRequests.push(r.url()))
  page.on('response', (r) => { if (r.url() === url && r.status() >= 400) failedRequests.push(`${r.status()} ${r.url()}`) })

  const resp = await page.goto(url, { waitUntil: 'networkidle' })
  if (!resp || resp.status() !== 200) throw new Error(`${target.key}: HTTP ${resp && resp.status()}`)

  await page.evaluate(() => document.fonts && document.fonts.ready)
  const section = await page.$('.pb-section')
  if (!section) throw new Error(`${target.key}: no rendered .pb-section (empty/placeholder render)`)

  // Content bounding box (union of pb-sections), clamped to the frozen viewport.
  const box = await page.evaluate(() => {
    const els = [...document.querySelectorAll('.pb-section')]
    if (!els.length) return null
    let top = Infinity, left = Infinity, right = 0, bottom = 0
    for (const el of els) {
      const r = el.getBoundingClientRect()
      top = Math.min(top, r.top); left = Math.min(left, r.left)
      right = Math.max(right, r.right); bottom = Math.max(bottom, r.bottom)
    }
    return { top: Math.max(0, top), left: Math.max(0, left), width: right - left, height: bottom - top }
  })
  if (!box || box.width < 40 || box.height < 20) throw new Error(`${target.key}: content box too small (${JSON.stringify(box)})`)
  if (box.width > VIEWPORT.width + 2) throw new Error(`${target.key}: horizontal overflow (${box.width}px)`)

  const clip = {
    x: box.left,
    y: box.top,
    width: Math.min(box.width, VIEWPORT.width - box.left),
    height: Math.min(box.height, VIEWPORT.height - box.top),
  }
  const png = await page.screenshot({ clip, type: 'png' })
  const renderHash = sha256(png)

  // Optimize to a fixed 4:3 WebP, metadata stripped (deterministic bytes). `contain`
  // (top-anchored, white letterbox) preserves ALL rendered content — a `cover` crop would
  // drop the left-aligned text of a wide/short section (hero/cta) and yield a blank card.
  const webp = await sharp(png)
    .resize({ width: OUT.width, height: OUT.height, fit: 'contain', position: 'top', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .webp({ quality: WEBP_QUALITY, effort: 4 })
    .toBuffer()

  if (consoleErrors.length) throw new Error(`${target.key}: console errors: ${consoleErrors.slice(0, 3).join(' | ')}`)
  if (failedRequests.length) throw new Error(`${target.key}: failed requests: ${failedRequests.slice(0, 3).join(' | ')}`)

  // Fail closed on a blank/near-uniform capture (no real content depicted).
  const stats = await sharp(webp).stats()
  const maxStdev = Math.max(...stats.channels.map((c) => c.stdev))
  if (maxStdev < 4) throw new Error(`${target.key}: blank/near-uniform thumbnail (max channel stdev ${maxStdev.toFixed(2)})`)

  return { ...target, file: `${target.key}.webp`, width: OUT.width, height: OUT.height, bytes: webp.length, renderHash, thumbHash: sha256(webp), webp }
}

async function main() {
  if (!EMAIL || !PASSWORD) throw new Error('PB_ADMIN_EMAIL and PB_ADMIN_PASSWORD are required.')
  mkdirSync(OUT_DIR, { recursive: true })
  mkdirSync(dirname(MANIFEST), { recursive: true })

  const browser = await chromium.launch()
  const context = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: DSF })
  const page = await context.newPage()
  await login(page)

  const entries = []
  for (const target of targets) {
    const r = await capture(page, target)
    writeFileSync(resolve(OUT_DIR, r.file), r.webp)
    const { webp, ...meta } = r
    entries.push(meta)
    console.log(`✓ ${r.key}  ${r.width}x${r.height}  ${r.bytes}B  thumb:${r.thumbHash.slice(0, 12)}`)
  }
  await browser.close()

  // Duplicate detection (distinct RIDs must not share an identical render).
  const byHash = {}
  for (const e of entries) (byHash[e.thumbHash] ??= []).push(e.key)
  const dups = Object.values(byHash).filter((k) => k.length > 1)
  if (dups.length) throw new Error(`Duplicate thumbnails: ${JSON.stringify(dups)}`)

  // The manifest is committed, so it records only the deterministic generation contract —
  // never the disposable runtime URL, credentials, or host paths.
  const manifest = { generator: 'page-builder/scripts/generate-thumbnails.mjs', viewport: VIEWPORT, deviceScaleFactor: DSF, output: OUT, webpQuality: WEBP_QUALITY, count: entries.length, thumbnails: entries }
  writeFileSync(MANIFEST, JSON.stringify(manifest, null, 2) + '\n')
  console.log(`\nWrote ${entries.length} thumbnails + manifest.`)
}

main().catch((e) => { console.error('GENERATION FAILED:', e.message); process.exit(1) })
