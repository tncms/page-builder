// Phase 26.1 — Pure builders for the layout node payloads the toolbar inserts.
//
// These mirror the backend canonical shapes (LayoutMutator::newColumn + the
// ContainerBlock defaults): ids are omitted so InsertComponentCommand regenerates
// them server-side. Kept pure (no Vue/Pinia) so both the store and tests can use
// them without a component mount.

/** An empty column node with the given percent-string width. */
export function emptyColumn(width: string): Record<string, unknown> {
  return { type: 'column', data: { width, blocks: [] } }
}

/**
 * A fresh container node. When `widths` is given, it is pre-filled with one empty
 * column per width (used by a Section's "Choose Layout"); otherwise it holds a
 * single default column, matching the ContainerBlock default children.
 */
export function newContainer(widths?: string[]): Record<string, unknown> {
  const columns =
    widths && widths.length > 0
      ? widths.map((width) => emptyColumn(width))
      : [emptyColumn('100')]
  return {
    type: 'container',
    data: {
      layout: 'flex',
      direction: 'row',
      gap: 'md',
      align: 'stretch',
      justify: 'start',
      blocks: columns,
    },
  }
}
