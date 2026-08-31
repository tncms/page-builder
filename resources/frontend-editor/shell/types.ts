// Phase 16 — Shell boot payload. Extends the structure-editor BootData with the
// frontend-specific fields the iframe canvas + top bar need.

import type { BootData } from '../../editor/types'

export interface FrontendBootData extends BootData {
  // The PRIVATE editor-document URL loaded into the canvas iframe. Identity-based
  // and authenticated — renders the draft even while the owner is unpublished, so
  // it never depends on the public slug being routable (PB-DRAFT-PREVIEW-R1).
  iframeUrl: string
  frontendOrigin: string
  // The owner's REAL public page URL — the "View Page" target, distinct from the
  // private iframe document. Never overload one field with both meanings.
  publicUrl: string
  // Whether "View Page" points at an accessible public page. Absent/false while the
  // content entity is unpublished, so the top bar disables the action. Defaults to
  // true when omitted, preserving the published-owner behaviour.
  publicUrlAvailable?: boolean
  structureEditUrl: string | null
}
