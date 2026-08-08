// Phase 26.20 — CMS Media Library browse client (integration, not invention).
//
// Reuses the EXISTING admin JSON endpoint `GET /admin/cms-media-search?q=&limit=` (backed by
// `MediaItems`/`MediaSearchController`), which returns image media as
// `{ data: [{ id, url, name, alt, title, width, height, seo_alt, seo_title, … }] }`. We do NOT
// add a new endpoint, storage, or media subsystem — this is a thin fetch + shape mapper.
//
// The endpoint and fetch implementation are injectable so the browser component stays testable
// and the integration is not hard-wired to a single URL.

/** One image from the Media Library search endpoint (only the fields the picker needs). */
export interface MediaLibraryItem {
  id: number
  url: string
  name: string
  alt: string
  width: number | null
  height: number | null
}

export interface MediaLibraryQuery {
  q?: string
  limit?: number
}

export interface MediaLibraryOptions {
  /** Override the search endpoint (defaults to the admin route). */
  endpoint?: string
  /** Override fetch (defaults to global fetch) — used in tests. */
  fetchImpl?: typeof fetch
}

/** The existing authenticated admin search route. */
export const DEFAULT_MEDIA_ENDPOINT = '/admin/cms-media-search'

/** Map one raw server row to a MediaLibraryItem (alt falls back to the SEO alt). */
function mapItem(raw: Record<string, unknown>): MediaLibraryItem {
  const num = (v: unknown): number | null => (typeof v === 'number' ? v : null)
  const str = (v: unknown): string => (typeof v === 'string' ? v : '')
  return {
    id: typeof raw.id === 'number' ? raw.id : Number(raw.id) || 0,
    url: str(raw.url),
    name: str(raw.name) || str(raw.filename),
    alt: str(raw.alt) || str(raw.seo_alt),
    width: num(raw.width),
    height: num(raw.height),
  }
}

/**
 * Search the CMS Media Library. Throws on a network error or a non-OK response so the caller
 * can surface an error state. Returns items with a resolvable `url` (what the picker stores).
 */
export async function searchMediaLibrary(
  query: MediaLibraryQuery = {},
  opts: MediaLibraryOptions = {},
): Promise<MediaLibraryItem[]> {
  const endpoint = opts.endpoint ?? DEFAULT_MEDIA_ENDPOINT
  const doFetch = opts.fetchImpl ?? fetch
  const params = new URLSearchParams()
  if (query.q) params.set('q', query.q)
  params.set('limit', String(query.limit ?? 50))

  const response = await doFetch(`${endpoint}?${params.toString()}`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })
  if (!response.ok) {
    throw new Error(`Media search failed (${response.status}).`)
  }
  const body = (await response.json()) as { data?: unknown }
  const rows = Array.isArray(body.data) ? body.data : []
  return rows
    .filter((r): r is Record<string, unknown> => !!r && typeof r === 'object')
    .map(mapItem)
    .filter((item) => item.url !== '')
}
