// Phase 26.7 — Token registry + resolver (frontend).
//
// Flattens the `metadata.tokens` map (`{ group: { name: value } }`) into typed
// `DesignToken`s and resolves reference strings to values — mirroring the PHP
// `TokenResolver`/`TokenRegistry`: a known reference resolves to its value; anything
// else (a non-reference, or an unknown key) passes through unchanged.

import type { DesignToken } from './tokenTypes'
import { humanize, tokenGroupOrder } from './tokenTypes'
import { parseTokenReference } from './tokenReference'

type TokenMap = Record<string, Record<string, unknown>>

export class TokenRegistry {
  private readonly tokens = new Map<string, DesignToken>()

  constructor(map: TokenMap = {}) {
    for (const [group, entries] of Object.entries(map ?? {})) {
      if (!entries || typeof entries !== 'object') continue
      for (const [name, value] of Object.entries(entries)) {
        const key = `${group}.${name}`
        this.tokens.set(key, { group, name, key, value, label: humanize(name) })
      }
    }
  }

  /** Every token, grouped-first then name order (as authored). */
  all(): DesignToken[] {
    return [...this.tokens.values()].sort(
      (a, b) => tokenGroupOrder(a.group) - tokenGroupOrder(b.group) || a.key.localeCompare(b.key),
    )
  }

  /** The distinct group ids present, in display order. */
  groups(): string[] {
    const seen = [...new Set(this.all().map((t) => t.group))]
    return seen.sort((a, b) => tokenGroupOrder(a) - tokenGroupOrder(b))
  }

  /** Tokens in one group. */
  byType(group: string): DesignToken[] {
    return this.all().filter((t) => t.group === group)
  }

  /** Whether a token key exists. */
  has(key: string): boolean {
    return this.tokens.has(key)
  }

  /** A token by key, or null. */
  get(key: string): DesignToken | null {
    return this.tokens.get(key) ?? null
  }

  /**
   * The key a value actually references AND that exists here — null for a literal or an
   * unknown key. Use this to tell a real token reference from a coincidental `a.b`
   * literal (e.g. `0.5rem`).
   */
  referencedKey(value: unknown): string | null {
    const key = parseTokenReference(value)
    return key !== null && this.tokens.has(key) ? key : null
  }

  /**
   * Resolve a value: a known reference → its token value; a non-reference or unknown
   * key → the input unchanged (PHP parity, so previewing is always safe).
   */
  resolve(value: unknown): unknown {
    const key = parseTokenReference(value)
    if (key === null) return value
    const token = this.tokens.get(key)
    return token ? token.value : value
  }
}
