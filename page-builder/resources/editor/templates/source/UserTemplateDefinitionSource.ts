// Phase 26.24 — User template definitions (source = 'user').
//
// User templates already live in the runtime `TemplateProvider` (written by authoring/management).
// This source DELEGATES to it — it never copies or re-stores definitions, so there is a single
// source of truth. It is read-only here.

import type { TemplateDefinition, TemplateProvider } from '../contracts'
import { templateProvider as defaultTemplateProvider } from '../provider'
import { TEMPLATE_SOURCE } from '../library'
import type { TemplateDefinitionSource } from './TemplateDefinitionSource'

export class UserTemplateDefinitionSource implements TemplateDefinitionSource {
  readonly source = TEMPLATE_SOURCE.user

  constructor(private readonly provider: TemplateProvider = defaultTemplateProvider) {}

  getDefinition(templateId: string): TemplateDefinition | null {
    return this.provider.get(templateId) ?? null
  }
}
