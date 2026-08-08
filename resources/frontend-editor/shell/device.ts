// Phase 16 — Device presets for the responsive preview toolbar (pure).
//
// Phase 16 only changes the iframe WIDTH; there is no per-device field editing
// yet. Widths are intentionally simple CSS values applied to the iframe element.

export type Device = 'desktop' | 'tablet' | 'mobile'

export const DEVICE_WIDTHS: Record<Device, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '390px',
}

export const DEVICES: Array<{ id: Device; label: string; icon: string }> = [
  { id: 'desktop', label: 'Desktop', icon: '🖥' },
  { id: 'tablet', label: 'Tablet', icon: '▭' },
  { id: 'mobile', label: 'Mobile', icon: '▯' },
]

export function isDevice(value: unknown): value is Device {
  return value === 'desktop' || value === 'tablet' || value === 'mobile'
}

/** CSS width for a device, falling back to full width for unknown values. */
export function deviceWidth(device: Device): string {
  return DEVICE_WIDTHS[device] ?? '100%'
}
