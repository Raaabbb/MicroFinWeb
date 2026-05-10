# Platform Theme Tokens

Scope: SaaS-owned platform surfaces only.

This theme applies to:

- `super_admin`
- platform shell and shared navigation
- platform auth and onboarding pages
- SaaS landing pages and SaaS-owned marketing pages

This theme does not apply to:

- `tenant_login`
- `admin_panel`
- tenant public websites
- tenant logos, tenant colors, tenant fonts, or tenant campaign styling

## Theme Modes

Light mode direction:

- Trustworthy, clear, and operational
- Warm-neutral surfaces with strong hierarchy
- Best for long-form review, daytime admin work, and high-density forms

Dark mode direction:

- Premium, authoritative, and secure
- Near-black platform shell with indigo-violet restraint
- Best for command-center framing, analytics, and platform identity

## Dark Mode Direction: Executive Vault

Design intent:

- Premium and authoritative, not playful
- Financially credible, secure, and controlled
- Tech-forward without neon, cyberpunk glow, or oversaturated purple

Core palette:

- Platform background: `#05070d`
- Elevated platform surface: `#0c1020`
- Primary surface: `#111627`
- Sidebar shell: `#080b14`
- Primary indigo accent: `#7268ff`
- Secondary violet accent: `#9a7bff`
- Main text: `#eef2ff`
- Muted text: `#99a3c3`
- Border: `#232a42`

## Recommendations

### SaaS platform background colors

- Use near-black for the application canvas: `#05070d`
- Use dark indigo for major structural surfaces: `#080b14`, `#0c1020`, `#111627`
- Use subtle radial gradients only for atmosphere, never as decoration-heavy hero effects

### Shared shell / navigation styling

- Keep the sidebar darker than content surfaces so the platform frame reads as the parent system layer
- Use soft glass or layered indigo treatments for the sticky top bar
- Reserve the indigo-violet accent for active nav items, page-level calls to action, and focus states

### Cards and surfaces

- Use restrained contrast jumps between `--bg-body`, `--bg-elevated`, and `--bg-card`
- Prefer crisp borders plus soft shadows over heavy glow
- Let important summary cards carry a faint indigo or violet wash instead of a bright fill

### Buttons and CTAs

- Primary CTAs should use indigo-to-violet gradients with controlled saturation
- Secondary buttons should stay dark and bordered, not flat white or bright outline colors
- Danger, success, and warning styles should remain semantic and should not become brand accents

### Text and muted text

- Main text should stay cool and near-white for authority and readability
- Muted copy should remain desaturated blue-gray, not low-contrast gray
- Headlines should use tighter tracking and stronger weight to reinforce hierarchy

### Borders and dividers

- Use cool indigo-gray borders such as `#232a42` or low-opacity white
- Dividers should separate structure without creating noise
- Selected states should lean on accent borders before using stronger fills

### Hover, focus, and selected states

- Hover: slight indigo surface lift or border intensification
- Focus: accessible accent ring using the primary indigo family
- Selected: combine accent border, subtle accent wash, and stronger text color

### Accent usage rules

- Indigo is the primary brand accent
- Violet is a secondary support accent for gradients, highlights, and emphasis
- Do not flood large areas with violet
- Avoid neon purple, pink-purple, or high-glow treatments
- Keep accent use concentrated on buttons, active tabs, focused fields, badges, charts, and key data cues

## Tenant Brand Independence Rules

- The SaaS brand must only own the platform shell, shared chrome, and SaaS-owned pages
- Tenant logos must appear only inside tenant-owned spaces or tenant-specific cards meant to preview tenant identity
- Tenant colors must not recolor the platform sidebar, platform top bar, platform auth pages, or SaaS marketing pages
- Tenant themes can style tenant dashboards, tenant login experiences, and tenant websites, but should sit inside the MicroFin platform frame rather than replacing it
- When tenant branding is shown inside platform pages, contain it inside cards, previews, or tenant records so it never becomes the global UI chrome
- Platform accent colors should never override or mutate tenant-owned branding assets

## Implementation Notes

- Shared theme variables live in `super_admin_theme.css`
- Platform auth styling lives in `super_admin_auth.css`
- Dashboard shell styling lives in `super_admin.css`
- SaaS marketing styling lives in `public_website/style.css`
- SaaS application styling lives in `public_website/demo.css`
- Tenant-facing styling in `tenant_login` and `admin_panel` remains independent by design
