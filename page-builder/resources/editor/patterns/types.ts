// Phase 25 — Pattern Library (editor side) types. Mirrors the PHP manifest
// resource returned by GET /api/page-builder/patterns.

// Phase 25.1 — variable + preview metadata. All are optional so patterns that
// predate this phase (no variables / no preview block) type-check unchanged.
export type PatternVariableType =
  | 'text'
  | 'textarea'
  | 'url'
  | 'image'
  | 'color'
  | 'select'
  | 'toggle'
  | 'number'

export interface PatternVariableOption {
  value: string
  label: string
}

export interface PatternVariable {
  id: string
  type: PatternVariableType
  label: string
  default: unknown
  required: boolean
  options: PatternVariableOption[]
}

export interface PatternPreview {
  type: string
  src: string
  available: boolean
  fallback: string
  url: string | null
  // Phase 25.2.x — optional per-breakpoint preview sources.
  responsive?: Record<string, string>
}

export interface PatternManifest {
  id: string
  name: string
  slug: string
  category: string
  description: string
  tags: string[]
  version: string
  author: string
  thumbnail: string
  thumbnail_url: string
  keywords: string[]
  difficulty: string
  featured: boolean
  premium: boolean
  previewWidth: number
  previewHeight: number
  // Phase 25.1 additions (optional for backward compatibility).
  variables?: PatternVariable[]
  preview?: PatternPreview
  schema?: number
  builder?: string
  created_at?: string
  updated_at?: string
  license?: string
  homepage?: string
  documentation?: string
  min_builder_version?: string
  deprecated?: boolean
  replacement?: string
  requiredComponents?: string[]
  // Phase 25.2 additions (optional for backward compatibility).
  dependencies?: PatternDependencies
  availability?: PatternAvailability
  pack?: string | null
}

/** User-entered variable values keyed by variable id. */
export type PatternVariableValues = Record<string, unknown>

// Phase 25.2 — dependency + pack metadata.
export interface PatternDependencies {
  plugins: string[]
  components: string[]
  capabilities: string[]
}

export interface PatternRequirement {
  type: string
  id: string
  label: string
  status: 'met' | 'missing'
  action: string
}

export interface PatternAvailability {
  available: boolean
  missing: {
    plugins: string[]
    components: string[]
    capabilities: string[]
  }
  // Phase 25.2.x — UI-ready requirement contract (met + missing).
  requirements?: PatternRequirement[]
}

export interface PatternPackInstallation {
  source: string
  version: string
  checksum: string
  installed_at: string
}

export interface PatternPack {
  id: string
  name: string
  version: string
  author: string
  license: string
  homepage: string
  website: string
  description: string
  premium: boolean
  price: number
  currency: string
  patterns: string[]
  count: number
  installation: PatternPackInstallation
}

export interface PatternPacksResult {
  packs: PatternPack[]
}

export interface PatternCategory {
  key: string
  name: string
  icon: string
  description: string
  order: number
  count: number
}

export interface PatternIndexResult {
  patterns: PatternManifest[]
  categories: PatternCategory[]
}

export interface PatternDetailResult {
  manifest: PatternManifest
  payload: { version?: number; sections: Array<Record<string, unknown>> }
}

export type PatternFilterMode = 'all' | 'featured' | 'favorites' | 'recent' | 'free' | 'premium'
