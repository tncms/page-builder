// Thin fetch wrapper for the editor endpoints (Phase 9). Sends the CSRF token
// and expects the standard { success, data } envelope.

import type {
  BootData,
  BuilderResourcePayload,
  ComponentPreviewResult,
  DesignCaptureResponse,
  EditorContextData,
  FullPageImportPlanData,
  FullPageImportResultData,
  LayoutContext,
  LayoutPayload,
  StateResult,
} from '../types'
import type { PatternDetailResult, PatternIndexResult, PatternPacksResult } from '../patterns/types'
import type { ResourceCatalogBundle } from '../browser/types'

/** The stable, namespaced conflict code the server returns on a stale save/publish. */
export const LAYOUT_VERSION_CONFLICT = 'page_builder.layout.version_conflict'

/**
 * A typed optimistic-concurrency conflict (INV-PUB-06). Distinct from a generic
 * request failure so the store can preserve local work and refuse a false success
 * instead of treating it as a transport error. Carries the server's current-version
 * metadata (token + state flags, never payloads) for explicit reload/reconciliation.
 */
export class LayoutConflictError extends Error {
  constructor(
    message: string,
    public readonly code: string,
    public readonly current: Record<string, unknown> | null,
  ) {
    super(message)
    this.name = 'LayoutConflictError'
  }
}

export class ApiClient {
  constructor(private readonly boot: BootData) {}

  async getContext(): Promise<EditorContextData> {
    return this.request<EditorContextData>(this.boot.api.context, 'GET')
  }

  async saveDraft(
    payload: LayoutPayload,
    token: string | null,
    provenanceAttestations: string[] = [],
  ): Promise<LayoutContext> {
    // Relay opaque provenance groups only when present, so a legacy save (no pending
    // groups) is byte-identical to before (PB-TREG-2). The client never parses them.
    const body: Record<string, unknown> = { payload, token }
    if (provenanceAttestations.length) body.provenanceAttestations = provenanceAttestations
    return this.request<LayoutContext>(this.boot.api.draft, 'POST', body)
  }

  /**
   * PB-TREG-2 mint-at-insert: insert a registered resource server-side (the server
   * dispatches the existing insert command, regenerates IDs once, and mints provenance
   * from the authoritative result) and receive the authoritative state plus an opaque
   * attestation group. Transport-only: the client sends only RID + placement intent +
   * current state + expected token, and never IDs/provider/provenance/payload.
   */
  async insertRegisteredResource(
    payload: LayoutPayload,
    token: string | null,
    rid: string,
    placement: { targetParentId?: string | null; index?: number | null } = {},
  ): Promise<
    StateResult & {
      artifacts?: { provenanceAttestations: string[]; rootIds: string[]; expiresAt: number; horizonAt: number }
    }
  > {
    const url = this.boot.api.insertRegisteredResource
    if (!url) throw new Error('provenance_endpoint_unavailable')
    return this.request(url, 'POST', {
      payload,
      token,
      rid,
      target_parent_id: placement.targetParentId ?? null,
      index: placement.index ?? null,
    })
  }

  /** Renew a complete opaque attestation group (operational expiry only). */
  async renewAttestations(attestations: string[]): Promise<{ attestations: string[] }> {
    const url = this.boot.api.attestationRenew
    if (!url) throw new Error('provenance_endpoint_unavailable')
    return this.request(url, 'POST', { attestations })
  }

  /** Narrow/rebind a complete opaque group to a strict subset of its roots, or discard. */
  async rebindAttestations(
    attestations: string[],
    retainedRootIds: string[],
  ): Promise<{ attestations: string[]; discarded?: boolean }> {
    const url = this.boot.api.attestationRebind
    if (!url) throw new Error('provenance_endpoint_unavailable')
    return this.request(url, 'POST', { attestations, retainedRootIds })
  }

  async publish(token: string | null): Promise<LayoutContext> {
    return this.request<LayoutContext>(this.boot.api.publish, 'POST', { token })
  }

  /**
   * Run a single Phase 8 command against a payload (stateless). Returns the full
   * updated BuilderState array.
   */
  async runCommand(
    payload: LayoutPayload,
    command: string,
    commandPayload: Record<string, unknown> = {},
  ): Promise<StateResult> {
    return this.request<StateResult>(this.boot.api.command, 'POST', {
      payload,
      command,
      command_payload: commandPayload,
    })
  }

  /** Render the payload to marker-annotated HTML for the visual canvas (Phase 12). */
  async renderPreview(payload: LayoutPayload, ownerType: string, ownerId: string | number): Promise<string> {
    const data = await this.request<{ html: string }>(this.boot.api.preview, 'POST', {
      owner_type: ownerType,
      owner_id: ownerId,
      payload,
      // The value locale must equal the editing locale so the preview resolves
      // translatable envelopes exactly as the public page will (PB-STATE-2T). The
      // server validates it (fail-closed) and scopes it around this render only.
      locale: this.boot.locale,
    })
    return data.html
  }

  /**
   * Render ONE component (by id) to marker-annotated HTML for the frontend Live
   * Builder (Phase 21). The URL is derived from the preview endpoint so no extra
   * boot plumbing is needed. Returns the raw endpoint result (html + metadata).
   */
  async renderComponentPreview(
    payload: LayoutPayload,
    componentId: string,
    ownerType: string,
    ownerId: string | number,
  ): Promise<ComponentPreviewResult> {
    const url = this.boot.api.preview.replace(/\/preview$/, '/component-preview')
    return this.request<ComponentPreviewResult>(url, 'POST', {
      owner_type: ownerType,
      owner_id: ownerId,
      payload,
      component_id: componentId,
      // Reconcile one component under the EDITING locale (PB-STATE-2T), so its
      // fields never render in the ambient admin locale. Server-validated + scoped.
      locale: this.boot.locale,
    })
  }

  /**
   * Pattern Library (Phase 25). The pattern endpoints share the command URL's
   * base, so derive them rather than threading more boot plumbing.
   */
  async getPatterns(): Promise<PatternIndexResult> {
    return this.request<PatternIndexResult>(this.patternsUrl(), 'GET')
  }

  /** Fetch ONE pattern's manifest + (lazy) Builder payload for preview / insert. */
  async getPatternPayload(id: string): Promise<PatternDetailResult> {
    return this.request<PatternDetailResult>(`${this.patternsUrl()}/${encodeURIComponent(id)}`, 'GET')
  }

  /**
   * Resolve a pattern's variables server-side (Phase 25.1) and return the plain
   * Builder payload ready to insert. The server merges the posted values over the
   * manifest defaults; omit values to get the defaults.
   */
  async getResolvedPatternPayload(
    id: string,
    variables: Record<string, unknown>,
  ): Promise<PatternDetailResult> {
    return this.request<PatternDetailResult>(
      `${this.patternsUrl()}/${encodeURIComponent(id)}/resolve`,
      'POST',
      { variables },
    )
  }

  /** List discovered pattern packs with their metadata + counts (Phase 25.2). */
  async getPatternPacks(): Promise<PatternPacksResult> {
    return this.request<PatternPacksResult>(`${this.patternsUrl()}/packs`, 'GET')
  }

  /**
   * The unified Resource Browser catalog (PB-TEMPLATE-REGISTRY-2-B). Metadata only —
   * no resource payload — resolved for the Admin UI locale server-side. Fetched lazily
   * when the browser opens. URL derived from the command endpoint (no extra boot plumbing).
   */
  async getResourceCatalog(): Promise<ResourceCatalogBundle> {
    const url = this.boot.api.command.replace(/\/state\/command$/, '/editor/resource-catalog')
    return this.request<ResourceCatalogBundle>(url, 'GET')
  }

  /**
   * PB-TEMPLATE-REGISTRY-2-C — Full Page Template import dry run (read-only). Resolves
   * the RID + current draft revision server-side and returns the plan's optimistic-
   * concurrency tokens. The plan endpoint always answers 200/{success:true}.
   */
  async planTemplateImport(body: {
    rid: string
    ownerType: string
    ownerId: number | string
  }): Promise<FullPageImportPlanData> {
    const url = this.boot.api.templateImportPlan
    if (!url) throw new Error('Template import is unavailable.')
    return this.request<FullPageImportPlanData>(url, 'POST', body)
  }

  /**
   * Atomic commit that replaces ONLY the draft. Returns the RAW envelope (does NOT throw
   * on a 422) so the caller can branch on typed blocker codes (stale_revision /
   * plan_mismatch → conflict) instead of treating them as a generic transport error.
   */
  async commitTemplateImport(body: {
    rid: string
    ownerType: string
    ownerId: number | string
    planHash: string
    revision: string
  }): Promise<{ status: number; data: FullPageImportResultData }> {
    const url = this.boot.api.templateImport
    if (!url) return { status: 0, data: { ok: false, issues: [] } }
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.boot.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })
    const json = await response.json().catch(() => null)
    const data = (json?.data ?? { ok: false, issues: [] }) as FullPageImportResultData
    return { status: response.status, data }
  }

  private patternsUrl(): string {
    return this.boot.api.command.replace(/\/state\/command$/, '/patterns')
  }

  /**
   * Resolve a Builder Resource's id-free payload for native insertion (Phase
   * 26.47E). The palette carries only light metadata; the tree is fetched here and
   * dispatched through the existing insert-pattern / insert-component commands.
   */
  async getBuilderResourcePayload(rid: string): Promise<BuilderResourcePayload> {
    const base = this.boot.api.command.replace(/\/state\/command$/, '/builder/resources')
    return this.request<BuilderResourcePayload>(`${base}?rid=${encodeURIComponent(rid)}`, 'GET')
  }

  /**
   * Save as Design (Phase 26.48). Returns the raw envelope (does NOT throw on a
   * non-2xx) so the dialog can branch on saved / duplicate / invalid.
   */
  async captureDesign(body: Record<string, unknown>): Promise<{ status: number; data: DesignCaptureResponse }> {
    const url = this.boot.api.command.replace(/\/state\/command$/, '/builder/capture')
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.boot.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })
    const json = await response.json().catch(() => null)
    const data = (json?.data ?? { status: 'invalid', issues: [] }) as DesignCaptureResponse
    return { status: response.status, data }
  }

  /** Run an ordered group of commands as one transaction (one undo step). */
  async runTransaction(
    payload: LayoutPayload,
    commands: Array<{ command: string; command_payload?: Record<string, unknown> }>,
    label = 'Transaction',
  ): Promise<StateResult> {
    const url = this.boot.api.command.replace(/\/command$/, '/transaction')
    return this.request<StateResult>(url, 'POST', { payload, label, commands })
  }

  private async request<T>(url: string, method: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.boot.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
    })

    const json = await response.json().catch(() => null)

    // INV-PUB-06 — a stale save/publish is a typed conflict, not a generic failure, so
    // the store can keep local work and refuse a false success.
    if (response.status === 409) {
      throw new LayoutConflictError(
        json?.message ?? 'This layout changed in another session.',
        json?.error ?? LAYOUT_VERSION_CONFLICT,
        (json?.data as Record<string, unknown>) ?? null,
      )
    }

    if (!response.ok || !json?.success) {
      throw new Error(json?.message ?? `Request failed (${response.status}).`)
    }

    return json.data as T
  }
}
