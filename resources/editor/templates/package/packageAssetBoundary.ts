// Phase 26.26 — asset portability boundary for imported packages (Phase 26.20.1).
//
// Templates carry URLs, not storage identity. An imported package must not smuggle in a media id,
// attachment id, or storage/disk reference — those are meaningless (or worse, cross-site data
// leaks) on the target install. This scans a template for such keys and rejects them, and checks
// the thumbnail `preview` is a safe URL. It never rewrites data; it only reports issues.

import { isSafeAssetUrl } from '../../assets'
import type { TemplateDefinition } from '../contracts'
import type { PackageIssue } from './templatePackageTypes'

/** Keys that denote storage identity rather than a portable URL. */
const STORAGE_IDENTITY_KEYS = new Set([
  'media_id',
  'mediaId',
  'attachment_id',
  'attachmentId',
  'storage',
  'storage_path',
  'storagePath',
  'disk',
  'file_id',
  'fileId',
])

/** Report storage-identity keys anywhere in a template, plus an unsafe thumbnail URL. */
export function collectAssetBoundaryIssues(def: TemplateDefinition): PackageIssue[] {
  const issues: PackageIssue[] = []
  const id = def?.id

  if (typeof def?.preview === 'string' && def.preview.trim() && !isSafeAssetUrl(def.preview.trim())) {
    issues.push({ code: 'unsafe-thumbnail', message: `Template "${id}" thumbnail is not a safe URL.`, templateId: id })
  }

  const seen = new Set<string>()
  const walk = (value: unknown): void => {
    if (Array.isArray(value)) {
      value.forEach(walk)
      return
    }
    if (value && typeof value === 'object') {
      for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
        if (STORAGE_IDENTITY_KEYS.has(key) && !seen.has(key)) {
          seen.add(key)
          issues.push({
            code: 'storage-identity',
            message: `Template "${id}" contains a storage reference "${key}" (templates must carry URLs, not ids).`,
            templateId: id,
          })
        }
        walk(child)
      }
    }
  }
  walk(def?.layout)

  return issues
}
