# Chef À La Carte — Design System

## Visual Direction

Restrained luxury. The menu communicates quality through precise typography,
generous white-space, and a controlled palette drawn from the brand logo.
No decorative gold, no oversized hero images, no dashboard styling.

## Colour Tokens

| Token               | Light                | Dark                 | Usage                    |
|----------------------|----------------------|----------------------|--------------------------|
| `--surface-primary`  | `#FDFAF5` (warm ivory)| `#1A1A1A`            | Page background          |
| `--surface-elevated` | `#FFFFFF`            | `#242424`            | Cards, dialogs           |
| `--surface-header`   | `#FDFAF5`/95% alpha  | `#1A1A1A`/95% alpha  | Sticky header            |
| `--brand-green`      | `#176023`            | `#2D8E3C`            | Logo green, accents      |
| `--brand-green-soft` | `#E8F2EA`            | `#1E3322`            | Category active bg       |
| `--text-primary`     | `#1A1A18`            | `#F0EDE8`            | Headings, prices         |
| `--text-secondary`   | `#5C5A56`            | `#A8A5A0`            | Descriptions, meta       |
| `--text-tertiary`    | `#8A8783`            | `#706D68`            | Calories, badges         |
| `--border`           | `#E8E5E0`            | `#333333`            | Dividers, card borders   |
| `--border-focus`     | `#176023`            | `#2D8E3C`            | Focus rings              |
| `--overlay`          | `rgba(0,0,0,0.45)`  | `rgba(0,0,0,0.6)`   | Dialog backdrop          |

## Typography

| Role         | Family              | Weight   | Size (mobile / desktop) |
|--------------|---------------------|----------|-------------------------|
| Display      | Playfair Display    | 600      | 22 / 28 px              |
| Category     | Playfair Display    | 600      | 16 / 18 px              |
| Product Name | Inter               | 600      | 15 / 16 px              |
| Price        | Inter               | 700      | 16 / 18 px              |
| Body         | Inter               | 400      | 14 / 15 px              |
| Caption      | Inter               | 400      | 12 / 13 px              |
| UI Control   | Inter               | 500      | 13 / 14 px              |

Turkish characters (ç, ğ, ı, ö, ş, ü, İ) fully supported by both families.
Arabic fallback: system `Noto Sans Arabic` or platform default.

Google Fonts loaded: `Playfair Display:wght@600` and `Inter:wght@400;500;600;700`.

## Spacing Scale

Based on 4 px grid: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 px.

| Token          | Value  |
|----------------|--------|
| `--space-xs`   | 4px    |
| `--space-sm`   | 8px    |
| `--space-md`   | 16px   |
| `--space-lg`   | 24px   |
| `--space-xl`   | 32px   |
| `--space-2xl`  | 48px   |

## Motion Tokens

| Token              | Value   | Usage                          |
|--------------------|---------|--------------------------------|
| `--duration-fast`  | 160ms   | Button states, focus rings     |
| `--duration-normal`| 220ms   | Category transitions           |
| `--duration-slow`  | 300ms   | Logo movement, detail sheet    |
| `--easing-default` | cubic-bezier(0.25, 0.1, 0.25, 1) | General transitions |
| `--easing-enter`   | cubic-bezier(0.0, 0.0, 0.2, 1)   | Elements entering    |
| `--easing-exit`    | cubic-bezier(0.4, 0.0, 1, 1)     | Elements leaving     |

All motion respects `prefers-reduced-motion: reduce`.

## Icon System

Close icons: inline SVG, 24×24 viewBox, 2px stroke, rounded caps.
Touch target: minimum 44×44 px with transparent padding.
No text glyphs or CSS-generated crosses.

## Logo Behavior

- Top of page: logo left-aligned within the header.
- After scroll threshold (80px): logo animates to center of compact sticky header.
- Uses `transform: translateX()` — no layout animation.
- Hysteresis: enter at 80px, exit at 40px to prevent flicker.
- `will-change: transform` applied during transition.
- Recalculates on `resize`.
- `prefers-reduced-motion`: instant repositioning, no animation.

## Layout

- Mobile-first single column.
- Max content width: 720px (menu), 1080px (desktop with side space).
- Category bar: horizontal scroll, no wrapping.
- Product cards: image left (64×64), text right, price far right.
- Detail dialog: bottom sheet on mobile, centered modal on desktop.
