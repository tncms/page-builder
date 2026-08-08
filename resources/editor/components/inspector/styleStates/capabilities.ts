// Phase 26.10 — component state capability detection.
//
// States are for interactive elements, NOT every component (per the brief — "do not
// enable all components blindly"). A component type opts in explicitly; everything else
// only ever sees `normal`. Seeded with the one real interactive block today (`button`);
// future interactive types (`link`, …) register through `registerStateCapableType`.

import { DEFAULT_STATE, STYLE_STATES, type StyleState } from './types'

/** Component TYPES that opt into style states. */
const stateCapableTypes = new Set<string>(['button'])

/** Whether a component type participates in style states. */
export function supportsStates(componentType: string): boolean {
  return stateCapableTypes.has(componentType)
}

/** Opt a component type into style states — the extensibility seam for new components. */
export function registerStateCapableType(componentType: string): void {
  stateCapableTypes.add(componentType)
}

/**
 * The states an editor should offer for a component: every state when capable, otherwise
 * just `normal` (so a non-capable component behaves exactly as it does today).
 */
export function statesFor(componentType: string): readonly StyleState[] {
  return supportsStates(componentType) ? STYLE_STATES : [DEFAULT_STATE]
}
