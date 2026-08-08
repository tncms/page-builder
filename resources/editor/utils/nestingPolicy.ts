// The one central composition nesting policy for the editor (Phase 25.3.2 §9/§10).
//
// Mirrors the PHP `NestingPolicy` exactly so the client preview and the
// server-authoritative mutation guard never disagree:
//   - a Container holds ONLY Columns;
//   - a Column holds ONLY ordinary elements (never a Column or a Container);
//   - Sections, legacy blocks and unknown/plugin types stay permissive.
//
// These two rules yield every required rejection (Element→Container,
// Container→Container, Column→Column, Container→Column) while allowing
// Section→Container, Container→Column and Column→Element.

export function canContain(parentType: string, childType: string): boolean {
  if (parentType === 'container') return childType === 'column'
  if (parentType === 'column') return childType !== 'column' && childType !== 'container'
  return true
}

/**
 * Whether an element dropped on the EDGE (before/after) of a container target
 * should be redirected INSIDE that container rather than placed as a sibling in
 * the container's parent (Phase 26.3.7 — nearest valid parent).
 *
 * This fixes the reported "drops in the wrong parent" bug: because sections are
 * permissively nestable, a Text dropped on the top/bottom of a Column that lives
 * directly inside a `columns` section otherwise commits as a SIBLING of the
 * columns (a direct child of the section) and renders outside the layout. An
 * element belongs inside the column it was dropped on. Columns/containers keep
 * sibling semantics so they can still be reordered next to one another; the rule
 * only fires for a non-layout element the container itself accepts.
 */
export function prefersInsideContainer(subjectType: string, containerType: string): boolean {
  if (subjectType === 'column' || subjectType === 'container') return false
  return canContain(containerType, subjectType)
}
