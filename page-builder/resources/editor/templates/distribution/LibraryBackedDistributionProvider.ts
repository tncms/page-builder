// Phase 26.33 — a distribution provider backed by an existing Template Library source.
//
// The single adapter that turns any Phase 26.21 `TemplateLibraryProvider` into a distribution
// provider. It ADDS NO STORAGE: `list`/`find` delegate straight to the wrapped library provider
// (which already returns source-scoped, immutable clones), so builtin/user/imported templates are
// exposed through distribution without duplication. `available` is injectable (default: always
// available) so a future reachable-check (remote/cloud) needs no new class.

import type { TemplateLibraryProvider, TemplateLibraryItem } from '../library'
import type { TemplateDistributionProvider } from './TemplateDistributionProvider'

export class LibraryBackedDistributionProvider implements TemplateDistributionProvider {
  constructor(
    private readonly sourceKey: string,
    private readonly library: TemplateLibraryProvider,
    private readonly isAvailable: () => boolean = () => true,
  ) {}

  source(): string {
    return this.sourceKey
  }

  available(): boolean {
    return this.isAvailable()
  }

  list(): TemplateLibraryItem[] {
    return this.library.all()
  }

  find(id: string): TemplateLibraryItem | null {
    return this.library.find(id) ?? null
  }
}
