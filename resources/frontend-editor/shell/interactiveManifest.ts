// PB-XREG-3 — the interactive manifest crosses the shell → iframe bridge through postMessage, which uses the
// structured-clone algorithm. Manifests held in the Pinia store are reactive Proxies and cannot be cloned
// (DataCloneError), so the bridge must carry a plain copy. The manifest is small, inert JSON (behavior ids and
// handle/kind/url entries); the iframe still validates every entry.
import type { InteractiveManifest } from '../../editor/types'

export function plainInteractiveManifest(manifest: InteractiveManifest | null | undefined): InteractiveManifest | null {
  if (!manifest) return null
  try {
    return JSON.parse(JSON.stringify(manifest)) as InteractiveManifest
  } catch {
    return null
  }
}
