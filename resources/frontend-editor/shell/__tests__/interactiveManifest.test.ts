import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import { plainInteractiveManifest } from '../interactiveManifest'
import type { InteractiveManifest } from '../../../editor/types'

// PB-XREG-3-R1 regression: the Live Builder shell posted the store's reactive manifest straight into postMessage,
// which threw DataCloneError and dropped every canvas update containing an interactive component.
const useProbe = defineStore('pbxManifestProbe', {
  state: () => ({ manifest: null as InteractiveManifest | null }),
})

const MANIFEST: InteractiveManifest = {
  behaviors: ['ecommerce:product-carousel'],
  assets: [
    { handle: 'pbx-runtime', kind: 'module', url: 'http://localhost/vendor/page-builder/interactive/runtime.js?v=abc' },
    { handle: 'pbx.ecommerce.product-carousel.carousel', kind: 'module', url: 'http://localhost/vendor/ecommerce/pb/product-carousel.js?v=def' },
  ],
}

describe('plainInteractiveManifest', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('turns a reactive store manifest into structured-clone-safe plain data', () => {
    const store = useProbe()
    store.manifest = MANIFEST
    const reactive = store.manifest

    expect(() => structuredClone(reactive)).toThrow()

    const plain = plainInteractiveManifest(reactive)
    expect(() => structuredClone(plain)).not.toThrow()
    expect(plain).toEqual(MANIFEST)
    expect(plain).not.toBe(reactive)
  })

  it('maps an absent manifest to null', () => {
    expect(plainInteractiveManifest(null)).toBeNull()
    expect(plainInteractiveManifest(undefined)).toBeNull()
  })
})
