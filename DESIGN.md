---
name: Cupo
description: Design system of Cupo, a single authenticated app (studio screens for every role, settings and auth) with no public site.
colors:
    paper: 'oklch(1 0 0)'
    ink: 'oklch(0.145 0 0)'
    charcoal: 'oklch(0.205 0 0)'
    chalk: 'oklch(0.985 0 0)'
    mist: 'oklch(0.97 0 0)'
    pencil-gray: 'oklch(0.556 0 0)'
    hairline: 'oklch(0.922 0 0)'
    focus-gray: 'oklch(0.87 0 0)'
    danger-red: 'oklch(0.577 0.245 27.325)'
typography:
    headline:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1.25rem'
        fontWeight: 600
        letterSpacing: '-0.025em'
    title:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1rem'
        fontWeight: 500
    body:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: 1.43
    label:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.875rem'
        fontWeight: 500
    caption:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.75rem'
        fontWeight: 500
rounded:
    sm: '6px'
    md: '8px'
    lg: '10px'
    xl: '14px'
spacing:
    xs: '4px'
    sm: '8px'
    md: '16px'
    lg: '24px'
    xl: '48px'
components:
    button-primary:
        backgroundColor: '{colors.charcoal}'
        textColor: '{colors.chalk}'
        rounded: '{rounded.md}'
        height: '36px'
        padding: '8px 16px'
    button-secondary:
        backgroundColor: '{colors.mist}'
        textColor: '{colors.charcoal}'
        rounded: '{rounded.md}'
        height: '36px'
        padding: '8px 16px'
    button-outline:
        backgroundColor: '{colors.paper}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        height: '36px'
        padding: '8px 16px'
    button-destructive:
        backgroundColor: '{colors.danger-red}'
        textColor: '{colors.paper}'
        rounded: '{rounded.md}'
        height: '36px'
        padding: '8px 16px'
    input:
        backgroundColor: '{colors.paper}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        height: '36px'
        padding: '4px 12px'
    card:
        backgroundColor: '{colors.paper}'
        textColor: '{colors.ink}'
        rounded: '{rounded.xl}'
        padding: '24px'
    badge:
        backgroundColor: '{colors.mist}'
        textColor: '{colors.charcoal}'
        rounded: '{rounded.md}'
        padding: '2px 8px'
---

# Design System: Cupo

## Overview

**Creative North Star: "The Club Ledger"**

Cupo is the studio's ledger made digital: the same calm, ruled order of the notebook kept at the front desk, with names, classes left and payments, but without its mistakes. Sessions, bookings, credits and cash read like entries in that ledger, so tables, aligned times and amounts, and literal labels carry the interface. Nothing decorates; precision is the brand.

The system is the incumbent shadcn/ui starter-kit world (new-york style) and stays so. It is achromatic: paper-white surfaces, ink text and a charcoal primary, with depth drawn by hairline borders and tonal fills instead of shadows. Density is moderate. The front desk uses it at a computer and on a phone, and students and instructors mostly on a phone, so it is compact without being cramped, and every screen must hold at both sizes. Light and dark modes are both first-class.

**Scope.** This file governs every surface of Cupo: the studio screens of all four roles (`/inicio`, `/estudios/{slug}/...`), account settings, the auth screens and the studio's enrollment page. There is no public site or second visual world. Every surface is built exclusively from the shadcn/ui components already in the starter kit (`resources/js/components/ui`, new-york style) and keeps their current look: screens are composed from those components as they ship, with no custom visual components, restyling or new visual language; missing primitives are added with the shadcn CLI, unmodified (if the CLI writes `import { cn } from "cn"`, change it to `@/lib/utils` and drop the `cn` dependency it adds).

**Key Characteristics:**

- Achromatic palette (OKLCH chroma 0) with a single semantic color, danger red.
- Instrument Sans throughout; hierarchy from size and weight, never from color.
- Flat surfaces separated by 1px hairlines and tonal fills; shadows only for minimal card lift and floating layers.
- Gently rounded corners (8–10px) on controls; 14px on cards.
- One strong primary action per screen; everything else is secondary, outline or ghost.

## Colors

A ledger palette: paper, ink and pencil grays, with red kept for danger alone.

### Primary

- **Charcoal**: the primary button, the selected navigation item and primary badges. In dark mode it inverts to chalk.

### Neutral

- **Paper**: page, card and popover background in light mode.
- **Ink**: body and heading text; dark-mode background.
- **Chalk**: text on charcoal; light-mode sidebar background.
- **Mist**: secondary buttons, hover fills, muted surfaces and the active navigation fill.
- **Pencil Gray**: secondary text: descriptions, helper text and placeholders.
- **Hairline**: borders, dividers and input strokes.
- **Focus Gray**: the focus ring color (applied at 50% opacity, 3px wide).

### Semantic

- **Danger Red**: delete actions, destructive dialogs, validation errors and invalid-field outlines.

### Named Rules

**The Red Means Delete Rule.** Danger red appears only on destructive actions, errors and invalid fields. It is never decoration, emphasis or a status accent.

**The No Blue Rule.** Cupo has no brand accent and adds no blue. Any future accent is a deliberate decision recorded in this file first, not something a component introduces.

**The Achromatic Ledger Rule.** State and hierarchy come from weight, size, fill and border, never from adding hue.

## Typography

**Body Font:** Instrument Sans (with ui-sans-serif, system-ui fallback), loaded in weights 400, 500 and 600.

**Character:** a single, clean grotesque doing every job; the ledger reads evenly, and emphasis comes from weight steps rather than a second family.

### Hierarchy

- **Headline** (600, 1.25rem, -0.025em): page and section titles (the `Heading` component).
- **Title** (500, 1rem): small headings inside settings or studio sections.
- **Body** (400, 0.875rem, line-height ≈1.43): default UI text, table cells, descriptions.
- **Label** (500, 0.875rem): form labels, buttons and navigation items.
- **Caption** (500, 0.75rem): badges and compact metadata.

### Named Rules

**The Weight Not Color Rule.** Typographic hierarchy uses weight (400/500/600) and size only. Secondary text uses pencil gray; nothing else changes color for emphasis.

**The Aligned Figures Rule.** Times, prices and cash amounts (CLP), credits and counts in tables align and use tabular figures, so columns read like a ledger.

## Layout

Content sits inside the starter's sidebar layout. The main area is capped at 80rem (`max-w-7xl`), centered, with 16px padding and 16px gaps between blocks. Settings-style forms constrain to about 36rem (`max-w-xl`) with 48px between sections. Spacing follows a 4px base (4, 8, 16, 24, 48). On small screens the sidebar collapses into a sheet, tables scroll horizontally inside their container, and form fields stack to full width.

## Elevation & Depth

Flat by default. Surfaces are separated by 1px hairline borders and tonal fills (mist on paper), not by elevation. Cards carry only a barely-there lift; real shadows belong to floating layers (dropdowns, popovers, dialogs) that genuinely sit above the page.

### Named Rules

**The Hairline First Rule.** Separate regions with a hairline border or a tonal fill before reaching for a shadow. A shadow means "this floats above the page".

## Shapes

Gently rounded and consistent: controls at 8px, the base radius at 10px, cards at 14px, small inner elements at 6px. Borders are always 1px hairline. Nothing is pill-shaped except avatars.

## Components

### Buttons

Discreet and firm: compact, unadorned, clear.

- **Shape:** gently rounded (8px), 36px tall (32px small, 40px large).
- **Primary:** charcoal fill with chalk text, 8px × 16px padding. One per screen, for the main action.
- **Secondary / Outline / Ghost:** mist fill, hairline-bordered paper, or transparent with a mist hover. Used for every non-primary action.
- **Destructive:** danger red fill with white text, only for delete and irreversible actions, always behind a confirmation dialog.
- **Hover / Focus:** hover softens the fill (90% opacity on primary); focus shows a 3px focus-gray ring at 50% opacity.

### Badges

- **Style:** caption type, 8px corners, mist fill (secondary) or hairline outline; charcoal fill only for the single most important state.
- **State:** statuses such as «Confirmada», «Por validar» or «Pronto» use neutral variants; red only for an error state.

### Cards / Containers

- **Corner Style:** 14px.
- **Background:** paper (ink in dark mode).
- **Shadow Strategy:** minimal lift only (see Elevation & Depth).
- **Border:** 1px hairline.
- **Internal Padding:** 24px vertical, 24px gap between children.

### Inputs / Fields

- **Style:** 1px hairline stroke, transparent background, 8px corners, 36px tall, 12px horizontal padding.
- **Focus:** the border darkens to focus gray and a 3px ring appears at 50% opacity.
- **Error / Disabled:** invalid fields take a danger-red border and a soft red ring, with the message below in Spanish; disabled fields drop to 50% opacity.

### Navigation

- **Style:** the starter sidebar on a chalk background, label-weight items with lucide icons, a mist fill for the active item, mist hover. On mobile the sidebar becomes a sheet. The menu changes by role (owner, front desk, instructor, student) and items not built yet show dimmed with a «Pronto» badge.

## Do's and Don'ts

### Do:

- **Do** keep every surface achromatic and let danger red mark only destructive actions and errors.
- **Do** show one primary (charcoal) button per screen and make every other action secondary, outline or ghost.
- **Do** separate regions with 1px hairlines and mist fills before considering a shadow.
- **Do** align times and CLP amounts in tables with tabular figures.
- **Do** verify every screen at phone width as well as desktop, in light and dark mode.

### Don't:

- **Don't** introduce blue or any new accent color without recording it in this file first.
- **Don't** use danger red for emphasis, status or decoration.
- **Don't** stack cards inside cards or add decorative shadows, gradients or icon tiles.
- **Don't** add a second typeface; hierarchy comes from Instrument Sans weights and sizes.
