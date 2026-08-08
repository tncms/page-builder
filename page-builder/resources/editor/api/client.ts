// Thin fetch wrapper for the editor endpoints (Phase 9). Sends the CSRF token
// and expects the standard { success, data } envelope.

import type {
  BootData,
  BuilderResourcePayload,
  ComponentPreviewResult,
  DesignCaptureResponse,
  EditorContextData,
  LayoutContext,
  LayoutPayload,
  StateResult,
} from '../types'
import type { PatternDetailResult, PatternIndexResult, PatternPacksResult } from '../patterns/types'

export class ApiClient {
  constructor(private readonly boot: BootData) {}

  async getContext(): Promise<EditorContextData> {
    return this.request<EditorContextData>(this.boot.api.context, 'GET')
  }

  async saveDraft(payload: LayoutPayload): Promise<LayoutContext> {
    return this.request<LayoutContext>(this.boot.api.draft, 'POST', { payload })
  }

  async publish(): Promise<LayoutContext> {
    return this.request<LayoutContext>(this.boot.api.publish, 'POST', {})
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

    if (!response.ok || !json?.success) {
      throw new Error(json?.message ?? `Request failed (${response.status}).`)
    }

    return json.data as T
  }
}
