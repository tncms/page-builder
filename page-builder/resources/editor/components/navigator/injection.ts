import type { ComputedRef, InjectionKey, Ref } from 'vue'

/**
 * Provides the editor-session locked-id set (Phase 26.3, Part 13) from the
 * Navigator tree root down to each {@link NavigatorNode}, so a row can render
 * its lock indicator without every node querying the store independently
 * (one computed for the whole tree — keeps large trees O(n), Part 18).
 */
export const NavigatorLocksKey: InjectionKey<ComputedRef<ReadonlySet<string>>> = Symbol('pb-navigator-locks')

/**
 * Provides the Navigator's keyboard focus pointer (Phase 26.3, Part 16/17) —
 * an `aria-activedescendant`-style roving id that is DISTINCT from selection —
 * from the tree root down to each row so a node can render its focus ring and
 * update focus on click.
 */
export interface NavigatorFocusApi {
  focusedId: ComputedRef<string | null>
  focus: (id: string) => void
}

export const NavigatorFocusKey: InjectionKey<NavigatorFocusApi> = Symbol('pb-navigator-focus')

/**
 * Bridges a keyboard "open context menu" request (Shift+F10 / ContextMenu key,
 * Phase 26.3, Part 4.1) from the tree root — which owns focus — to the individual
 * row that owns its menu. The `nonce` makes repeated requests for the same id
 * re-trigger the watcher.
 */
export interface NavigatorMenuRequest {
  id: string
  nonce: number
}

export const NavigatorMenuRequestKey: InjectionKey<Ref<NavigatorMenuRequest | null>> = Symbol('pb-navigator-menu-request')

/**
 * Bridges a keyboard "begin inline rename" request (F2, Phase 26.3, Increment 5)
 * from the tree root — which owns roving focus — to the focused row that owns its
 * rename input. Same shape/nonce mechanism as {@link NavigatorMenuRequest}.
 */
export const NavigatorRenameRequestKey: InjectionKey<Ref<NavigatorMenuRequest | null>> = Symbol('pb-navigator-rename-request')
