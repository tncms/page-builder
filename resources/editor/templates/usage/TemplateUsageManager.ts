// Phase 26.32 — Template Usage Manager (the connector, framework-free).
//
// The single seam that connects Phase 26.31 adoption DISCOVERY to the existing Phase 26.23 apply
// flow. Given an adopted template id, it looks the item up in the discovery provider, translates it
// to a `TemplateSelection`, and HANDS OFF to an injected apply-requester — the existing apply store
// that owns confirmation, the runtime, the single command, and the one undo. It deliberately does
// NOT: resolve a template, apply, run a command, touch BuilderState, or model a multi-step wizard.
// No TemplateExecutionService / TemplateWorkflowEngine — usage is a connection, not a new runtime.

import { adoptionDiscovery, type AdoptionDiscoveryProvider } from '../adoptionDiscovery'
import type { TemplateSelection } from '../picker'
import { toUsageSelection, type UsageIntentResult } from './templateUsageIntent'

/** Opens the existing apply flow for a selection (e.g. `useTemplateApplyStore().request`). */
export type ApplyRequester = (selection: TemplateSelection) => void

export class TemplateUsageManager {
  constructor(
    private readonly discovery: AdoptionDiscoveryProvider = adoptionDiscovery,
    private readonly requestApply: ApplyRequester = () => {},
  ) {}

  /**
   * Begin using an adopted template: resolve it via discovery, and on success hand the selection to
   * the apply-requester (which opens the existing confirm → apply flow). Applies nothing itself.
   * `not-found` = no such adopted template; `unavailable` = adopted but unresolvable in the library.
   */
  use(id: string): UsageIntentResult {
    const item = this.discovery.find(id)
    if (!item) return { ok: false, reason: 'not-found' }
    const selection = toUsageSelection(item)
    if (!selection) return { ok: false, reason: 'unavailable' }
    this.requestApply(selection)
    return { ok: true, selection }
  }

  /**
   * Use a template the caller already holds as a `TemplateSelection` — e.g. the Phase 26.34 library
   * workspace, which browses the full library (not just adopted templates). Hands the selection to
   * the apply-requester; applies nothing itself.
   */
  useSelection(selection: TemplateSelection): void {
    this.requestApply(selection)
  }
}
