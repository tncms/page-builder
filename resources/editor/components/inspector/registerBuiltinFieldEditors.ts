// Phase 26.4 — Register the built-in property editors.
//
// The one place the first-party editors are wired into the registry. Importing this
// module registers them (idempotent). Kept separate from the registry itself so the
// registry stays `.vue`-free and importable from inside an editor component.

import { registerFieldEditor } from './fieldEditorRegistry'
import TextField from './fields/TextField.vue'
import TextareaField from './fields/TextareaField.vue'
import ToggleField from './fields/ToggleField.vue'
import SelectField from './fields/SelectField.vue'
import NumberField from './fields/NumberField.vue'
import ColorField from './fields/ColorField.vue'
import UrlField from './fields/UrlField.vue'
import MediaField from './fields/MediaField.vue'
import SpacingField from './fields/SpacingField.vue'
import TypographyField from './fields/TypographyField.vue'
import BorderField from './fields/BorderField.vue'
import BackgroundField from './fields/BackgroundField.vue'
import LinkField from './fields/LinkField.vue'
import ResponsiveField from './fields/ResponsiveField.vue'

let registered = false

/** Register the built-in editors once. Safe to call repeatedly. */
export function registerBuiltinFieldEditors(): void {
  if (registered) return
  registered = true

  registerFieldEditor('text', { component: TextField })
  registerFieldEditor('textarea', { component: TextareaField })
  registerFieldEditor('toggle', { component: ToggleField })
  registerFieldEditor('color', { component: ColorField })
  registerFieldEditor('url', { component: UrlField })
  registerFieldEditor('spacing', { component: SpacingField })
  registerFieldEditor('typography', { component: TypographyField })
  // Phase 26.5 — advanced editors.
  registerFieldEditor('border', { component: BorderField })
  registerFieldEditor('background', { component: BackgroundField })
  registerFieldEditor('link', { component: LinkField })

  // Editors that read schema metadata beyond the raw value.
  registerFieldEditor('select', { component: SelectField, needsField: true })
  registerFieldEditor('number', { component: NumberField, needsField: true })
  // Phase 26.5 — `media` reads `editor.alt`/`accept`/`multiple` from the schema.
  registerFieldEditor('media', { component: MediaField, needsField: true })
  // Phase 26.4 — `responsive` wraps a base field (its editor is resolved from this
  // same registry); it needs the field to find the inner descriptor.
  registerFieldEditor('responsive', { component: ResponsiveField, needsField: true })
}

// Register on import so a bare `import './registerBuiltinFieldEditors'` is enough.
registerBuiltinFieldEditors()
