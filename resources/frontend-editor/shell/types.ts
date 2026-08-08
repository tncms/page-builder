// Phase 16 — Shell boot payload. Extends the structure-editor BootData with the
// frontend-specific fields the iframe canvas + top bar need.

import type { BootData } from '../../editor/types'

export interface FrontendBootData extends BootData {
  iframeUrl: string
  frontendOrigin: string
  publicUrl: string
  structureEditUrl: string | null
}
