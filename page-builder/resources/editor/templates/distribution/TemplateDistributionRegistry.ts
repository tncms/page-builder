// Phase 26.33 — the distribution provider registry (deterministic, identity-strict).
//
// Holds the distribution providers keyed by their source. Registration order is preserved so
// `all()` / `sources()` are DETERMINISTIC — the builtin provider always lists first. A duplicate
// source key is rejected (identity-strict); the registry never silently overwrites a source. It is
// a lookup only: no resolution, no apply, no command, no BuilderState.

import type { TemplateDistributionProvider } from './TemplateDistributionProvider'

/** Thrown when a source key would be registered twice. */
export class DistributionRegistryError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'DistributionRegistryError'
  }
}

export class TemplateDistributionRegistry {
  private readonly providers = new Map<string, TemplateDistributionProvider>()

  /** Register a provider under its `source()` key. Rejects a duplicate source. */
  register(provider: TemplateDistributionProvider): void {
    const source = provider.source()
    if (this.providers.has(source)) {
      throw new DistributionRegistryError(`Distribution source "${source}" is already registered.`)
    }
    this.providers.set(source, provider)
  }

  has(source: string): boolean {
    return this.providers.has(source)
  }

  get(source: string): TemplateDistributionProvider | undefined {
    return this.providers.get(source)
  }

  /** Every provider, in registration order (deterministic). */
  all(): TemplateDistributionProvider[] {
    return [...this.providers.values()]
  }

  /** Only providers whose source is currently available, in registration order. */
  available(): TemplateDistributionProvider[] {
    return this.all().filter((provider) => provider.available())
  }

  /** Every registered source key, in registration order. */
  sources(): string[] {
    return [...this.providers.keys()]
  }
}
