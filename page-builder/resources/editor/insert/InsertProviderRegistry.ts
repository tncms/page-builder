// Phase 26.37 — the insert provider registry (deterministic, identity-strict).
//
// Holds the insert providers keyed by category. Registration order is preserved so `all()` /
// `keys()` are DETERMINISTIC. A duplicate key is rejected — the registry never silently overwrites a
// category. Lookup only: no resolution, no apply, no command, no BuilderState.

import type { InsertProvider } from './InsertProvider'

/** Thrown when a category key would be registered twice. */
export class InsertRegistryError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'InsertRegistryError'
  }
}

export class InsertProviderRegistry {
  private readonly providers = new Map<string, InsertProvider>()

  /** Register a provider under its `key`. Rejects a duplicate category. */
  register(provider: InsertProvider): void {
    if (this.providers.has(provider.key)) {
      throw new InsertRegistryError(`Insert category "${provider.key}" is already registered.`)
    }
    this.providers.set(provider.key, provider)
  }

  has(key: string): boolean {
    return this.providers.has(key)
  }

  get(key: string): InsertProvider | undefined {
    return this.providers.get(key)
  }

  /** Every provider, in registration order (deterministic). */
  all(): InsertProvider[] {
    return [...this.providers.values()]
  }

  /** Every registered category key, in registration order. */
  keys(): string[] {
    return [...this.providers.keys()]
  }

  /** Drop every provider (used when re-wiring). */
  clear(): void {
    this.providers.clear()
  }
}
