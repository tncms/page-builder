// Phase 26.24 — resolve a definition across sources by dispatching on the source KEY.
//
// The single entry the runtime binding uses. It picks the `TemplateDefinitionSource` whose key
// matches and asks it for the definition. An UNKNOWN source KEY is an explicit failure
// (`unsupported_template_source`) — never confused with "known source, no such template" (null).
// It performs no apply, no pattern resolution, no BuilderState work.

import type { TemplateDefinition } from '../contracts'
import { TemplateRuntimeError } from '../runtime'
import type { TemplateDefinitionProvider, TemplateDefinitionSource } from './TemplateDefinitionSource'

export class TemplateSourceResolver implements TemplateDefinitionProvider {
  private readonly sources = new Map<string, TemplateDefinitionSource>()

  constructor(sources: TemplateDefinitionSource[] = []) {
    for (const source of sources) this.sources.set(source.source, source)
  }

  /** The source KEYS this resolver can answer for. */
  knownSources(): string[] {
    return [...this.sources.keys()]
  }

  /**
   * The definition for (templateId, source), or null when the (known) source has no such template.
   * Throws `unsupported_template_source` when the source KEY is not registered.
   */
  getDefinition(templateId: string, source: string): TemplateDefinition | null {
    const src = this.sources.get(source)
    if (!src) {
      throw new TemplateRuntimeError('unsupported_template_source', `Unknown template source "${source}".`, {
        source,
        known: this.knownSources(),
      })
    }
    return src.getDefinition(templateId)
  }
}
