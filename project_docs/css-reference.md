# CSS Reference — app.css

## Design Identity

Warm, bold palette built on golden yellow, terracotta, and deep navy. The aesthetic feels like a retro photo app — rich, saturated, and confident. Typography mixes a serif display font with a system sans-serif body.

---

## Color Palette

| Token | Value | Role |
|---|---|---|
| `--color-bg` | `#F2CD5C` | Page background (golden yellow) |
| `--color-surface` | `#F2A766` | Cards, panels, auth box (terracotta) |
| `--color-accent` | `#D94179` | CTAs, active states, focus ring |
| `--color-navy` | `#141259` | Nav bar, secondary buttons, headings |
| `--color-deep` | `#010626` | Canvas background, darkest layer |
| `--color-text` | `#010626` | Body text |
| `--color-text-muted` | `#141259` | Labels, subtitles |
| `--color-text-on-dark` | `#F2CD5C` | Text on navy backgrounds |
| `--color-text-on-accent` | `#ffffff` | Text on pink buttons |
| `--color-error` | `#c0184a` | Inline errors |
| `--color-success` | `#1a7a4a` | Success states |
| `--color-focus` | `#D94179` | Focus outline (matches accent) |

Hover variants exist for surface (`#f09850`), accent (`#b8305f`), and navy (`#0e0b40`). Semi-transparent values are used for borders (`rgba(20,18,89,0.22)`), overlays (`rgba(1,6,38,0.55)`), and error backgrounds.

---

## Typography

Three font stacks:

- `--font-display` — Georgia / Times New Roman serif → headings (`h1–h4`), nav brand, home title
- `--font-body` — system-ui / -apple-system / Segoe UI sans-serif → body, buttons, inputs
- `--font-mono` — Courier New → editor param values

Scale (8 steps): `0.75rem` (xs) → `0.875rem` → `1rem` → `1.125rem` → `1.375rem` → `1.75rem` → `2.25rem` → `3rem` (4xl). The home title uses `clamp(3rem, 10vw, 6rem)` for fluid sizing.

---

## Spacing

Token-based scale mapped to multiples of `0.25rem`:

`--space-1` (0.25) · `--space-2` (0.5) · `--space-3` (0.75) · `--space-4` (1) · `--space-5` (1.25) · `--space-6` (1.5) · `--space-8` (2) · `--space-10` (2.5) · `--space-12` (3) · `--space-16` (4rem)

At `≤48em`, `--space-6` and `--space-8` are overridden slightly smaller.

---

## Shape & Shadow

**Border radii:** `sm` 0.25 · `md` 0.5 · `lg` 0.875 · `xl` 1.375 · `full` 624.9375rem (pill). Pills are used heavily — buttons, avatars, toasts, search input.

**Shadows:** Four levels using `rgba(1,6,38,…)` and `rgba(20,18,89,…)`:
- `--shadow-sm` — subtle lift
- `--shadow-md` — hover state on buttons
- `--shadow-lg` — modals, toasts, sticky panels
- `--shadow-card` — dual-layer card shadow

---

## Transitions

Three speeds: `fast` 120ms · `base` 200ms · `slow` 350ms — all `ease`. Used consistently across `background-color`, `color`, `border-color`, `box-shadow`, and `transform`.

---

## Layout

- `--max-width`: `60rem` — main container
- `--max-width-narrow`: `30rem` — feed and auth
- `--nav-height`: `3.5rem` — fixed nav; `body` has matching `padding-top`
- `.container` — `width:100%`, centered with `margin-inline: auto`, `padding-inline: var(--space-6)`

---

## Component Map

### Navigation (`.nav`)
Fixed, `z-index: 100`, navy background. Contains brand (display font), nav links, a search widget, and a logout ghost button. Links use `aria-current="page"` for active state. Search dropdown is absolutely positioned below the input with `z-index: 200`.

### Buttons (`.btn`)
Pill-shaped (`radius-full`), `inline-flex`, `font-weight: 600`. Three variants:
- **primary** — accent pink fill
- **secondary** — navy fill, yellow text
- **ghost** — transparent with navy border, fills navy on hover

Active state: `translateY(1px)`. Disabled: `opacity: 0.45`, `pointer-events: none`.

### Forms (`.form`)
Column flex with `gap: space-5`. Labels are uppercase, tracked, muted. Inputs have a translucent yellow bg (`rgba(242,205,92,0.45)`) that brightens on focus, a navy border that switches to accent pink on focus, and a pink glow ring (`box-shadow: 0 0 0 3px rgba(217,65,121,0.18)`). Global errors use `display:none` toggled by `:not(:empty)`.

### Feed (`.feed`)
Narrow max-width (`30rem`), column flex of cards. Cards (`surface` bg, `radius-lg`) lift `translateY(-2px)` with stronger shadow on hover. Images are `aspect-ratio: 4/3`, `object-fit: cover`, with a slow scale zoom on hover.

### Profile (`.profile`)
Full `max-width` layout. Header is horizontal flex (avatar + info), collapses to centered column on mobile. Post grid is `3 columns` → `2 columns` on tablet/mobile. Stat values are accented pink.

### Editor (`.editor`)
Two-column grid: fluid preview panel + fixed `21.25rem` controls sidebar. Sidebar is navy-colored, sticky (`top: space-6`), scrollable with a custom 5px scrollbar. Filter buttons, a step list with up/down/delete controls, and parameter sliders (custom-styled `range` inputs with pink thumb + scale-on-hover effect). A toast notification (`position: fixed`, pill-shaped, navy) animates in with `@keyframes toast-in` (fade + slide up).

---

## Responsive Breakpoints

**`≤48em` (tablet):**
- Editor switches to single column; controls panel loses sticky/max-height
- Profile header stacks vertically
- Profile grid drops to 2 columns
- Spacing tokens slightly reduced

**`≤30em` (mobile):**
- Nav gets tighter padding, smaller brand font and link text
- Nav search input shrinks to `max-width: 8rem`
- Editor preview actions stack vertically, buttons go full-width
- Home title hardcapped at `3rem`

---

## Utilities & Accessibility

- `.sr-only` — visually hidden but accessible to screen readers (standard clip technique)
- `.u-hidden` — `display: none`
- `:focus-visible` — `2px solid accent` outline with `border-radius: sm`; suppressed for mouse via `:focus:not(:focus-visible)`
- `scroll-behavior: smooth` on `html`
- `appearance: none` on inputs for cross-browser consistency
