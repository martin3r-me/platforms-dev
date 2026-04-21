# Dev Module — GitHub Design Brief

## Design Personality
**GitHub Clone** — Flat, clean, border-driven, monochrome with green accents.

## Color Palette (Tailwind Classes)

### Backgrounds
- Page content: `bg-white` (light) / `bg-gray-900` (dark)
- Surface cards: `bg-white` with `border border-gray-200`
- Hover rows: `hover:bg-gray-50`
- Muted backgrounds: `bg-gray-50`, `bg-gray-100`
- Selected/active: `bg-blue-50`

### Text
- Primary text: `text-gray-900`
- Secondary text: `text-gray-700`
- Muted text: `text-gray-500`
- Links: `text-blue-600 hover:text-blue-700 hover:underline`

### Borders
- Default: `border-gray-200` (1px, visible, structural)
- Subtle: `border-gray-100`
- No shadows — borders are the primary visual separator

### Accent Colors
- Green primary (CTA): `bg-green-600 hover:bg-green-700 text-white` (#238636)
- Blue links: `text-blue-600`
- Danger/bugs: `text-red-600`, `bg-red-50`
- Warning/overdue: `text-yellow-600`, `bg-yellow-50`
- Success/done: `text-green-600`, `bg-green-50`

## Component Patterns

### Buttons
```html
<!-- Primary (Green) -->
<button class="inline-flex items-center gap-2 px-4 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">

<!-- Secondary (Gray Outline) -->
<button class="inline-flex items-center gap-2 px-4 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">

<!-- Danger -->
<button class="inline-flex items-center gap-2 px-4 py-1.5 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition-colors">
```

### Labels/Badges
```html
<!-- Colored pill with dot -->
<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
    Active
</span>

<!-- Counter badge -->
<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">42</span>
```

### List Rows
```html
<div class="px-4 py-3 border-b border-gray-200 hover:bg-gray-50 transition-colors">
```

### Monospace Elements
```html
<code class="px-1.5 py-0.5 text-xs font-mono bg-gray-100 text-gray-700 rounded">abc1234</code>
```

### Avatars
```html
<!-- With image -->
<img class="w-5 h-5 rounded-full" src="..." alt="">

<!-- Initials fallback -->
<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-[10px] font-medium text-gray-600">M</span>
```

### Section Headers
```html
<div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
    <h3 class="text-sm font-semibold text-gray-900">Section Title</h3>
    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">12</span>
</div>
```

### Tab Navigation
```html
<nav class="flex border-b border-gray-200">
    <a class="px-4 py-2 text-sm font-medium text-gray-900 border-b-2 border-orange-500">Active</a>
    <a class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">Inactive</a>
</nav>
```

## Rules
1. **No CSS variables** — `var(--ui-*)` is forbidden in content areas
2. **No shadows** — Use borders exclusively for visual separation
3. **No gradients** — Flat solid colors only
4. **Monospace** for hashes, branches, code references
5. **Page shell components stay** — `x-ui-page`, `x-ui-page-navbar`, etc.
6. **x-ui-modal stays** — Part of the overlay system
7. **Everything else is custom** — No `x-ui-button`, `x-ui-badge`, `x-ui-panel`, `x-ui-table`, `x-ui-dashboard-tile`
