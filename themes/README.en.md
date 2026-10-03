# ioDek themes

*[Version française](README.md)*

Color themes for the [ioDek](https://iodek.fr) dashboard (`iodek.fr/bord`), the full-screen view built for the Tesla browser. A theme changes the colors, the card radius and the spacing; it changes neither the layout nor the text.

- `themes/`: ioDek's built-in themes (ioDek, Nuit bleue, Graphite, Tesla clair, Contraste élevé);
- `exemple.css`: a commented theme to start from;
- `scripts/check-theme.php`: checks a file with the exact rules of the ioDek server;
- `images/`: where each variable shows on screen.

## Format

A theme is a `.css` file that contains **only** `--b-…` variable declarations, inside a `.bord { … }` (or `:root { … }`) block, and comments. The first `ioDek theme: …` comment gives the theme name (40 characters at most).

```css
/* ioDek theme: My theme */
.bord {
    --b-bg: #10131a;
    --b-tile: #1a1f2b;
    --b-text: #f1f4fa;
    --b-cyan: #7cc4ff;
    --b-warn: hsl(4, 85%, 66%);
    --b-radius: 12px;
}
```

Missing variables keep their ioDek value. Maximum size: 16 KB.

### Accepted values

| Type | Values |
| --- | --- |
| Color | `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`; `rgb()`, `rgba()`, `hsl()`, `hsla()` (commas or spaces, alpha with `/`); `transparent`, `black`, `white` |
| Length | `px` or `rem` (1 rem = 16 px), bounded: `--b-radius` 0 to 32 px, `--b-gap` 4 to 32 px |

### What is refused

Free-form CSS could leak data or imitate the interface. The server therefore parses the file and keeps only the allowed values; **anything else rejects the file**, with the offending line:

- `@import`, `@media`, `@font-face` and every `@` rule;
- any selector other than `.bord` and `:root` (`body`, `*`, `.b-tile`…);
- any property that is not a known variable (`color`, `position`, `content`, `display`…);
- an unknown variable (`--b-foo`, `--other`);
- `url()`, `expression()`, `var()`, `calc()`, `image-set()` and any function other than `rgb()`, `rgba()`, `hsl()`, `hsla()`;
- `!important`, quoted strings, `\` escapes, nested blocks;
- a color or length outside the forms above, or out of bounds.

An accepted theme is stored as values (JSON), never as CSS, and applied as variables in an inline style: no CSS file is ever served as is.

## Variables

Values shown are those of the ioDek theme.

![Dashboard variables](images/variables-bord.png)

![Edit mode variables](images/variables-edition.png)

### Surfaces and text

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-bg` | `#00070f` | Screen background |
| `--b-tile` | `#051526` | Card, tab and top-bar button background |
| `--b-line` | `#11293f` | Card and button border |
| `--b-text` | `#eaf3fb` | Main text, figures |
| `--b-dim` | `#8ba2ba` | Secondary text: labels, units, data status |
| `--b-muted` | `#9fb6cc` | Muted text: Boost included, Tesla screen preview note |

### Accent colors

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-cyan` | `#21d0fd` | Main accent: active tab (tinted), active state, primary buttons, switches |
| `--b-amber` | `#f4a340` | Charging, power delivered, highest cell, warning |
| `--b-blue` | `#4d8dff` | Car asleep, lowest cell |
| `--b-warn` | `#f07167` | Alert, error, connection lost |
| `--b-green` | `#0ed8bd` | Green for thresholds and links |
| `--b-on-accent` | `#001526` | Text on the accent (primary button) |

Tinted backgrounds (active tab, badges, banners) are derived from the matching accent.

### Data status dot

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-live` | `#2fd27a` | Live data |
| `--b-idle` | `#6b7f93` | Car asleep, waiting |

### Gauges and bars

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-track` | `#0b2135` | Track of gauges, bars and switches |
| `--b-bar-start` | `#0f54cc` | Battery bar (and energy flow battery): gradient start |
| `--b-bar-mid` | `#21d0fd` | Gradient middle |
| `--b-bar-end` | `#0ed8bd` | Gradient end |
| `--b-band-bg` | `#0a1d30` | Cell balance: band background |
| `--b-band` | `#1b5170` | Cell balance: regular band |

### Controls

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-button` | `#081b2d` | Command button background |
| `--b-press` | `#0c2236` | Pressed button |
| `--b-icon` | `#a9bccf` | Idle icon (commands, Home Assistant, catalog) |
| `--b-field` | `#06121f` | Car picker drop-down |
| `--b-peek` | `rgba(4, 14, 24, .92)` | Status strip on an icon-only button |

### Edit mode, panels, preview

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-raise` | `#0b1f33` | Card handles, short message |
| `--b-outline` | `#1d3a57` | Dashed card outline in edit mode, indicator light border |
| `--b-panel` | `#061727` | Panel background (settings, catalog, theme) |
| `--b-panel-line` | `#1a3653` | Borders inside panels |
| `--b-seg-on` | `#11304b` | Selected section in a panel |
| `--b-hover` | `#2c5a85` | Hover in the icon picker |
| `--b-selected` | `#ffffff` | Outline of the selected color |
| `--b-shadow` | `rgba(0, 0, 0, .6)` | Shadows (short message, dragging) |
| `--b-backdrop` | `rgba(0, 0, 0, .7)` | Veil behind panels, panel shadow |
| `--b-overlay` | `rgba(0, 6, 14, .92)` | Tesla screen preview veil |
| `--b-frame` | `#2a3b4f` | Tesla screen preview frame |

### Energy flow

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-flow-drive` | `#21d0fd` | Flow while driving |
| `--b-flow-regen` | `#0ed8bd` | Regeneration |
| `--b-flow-charge` | `#5b9bff` | Charging, charger |
| `--b-flow-heat` | `#f4a340` | Battery heating |
| `--b-flow-body` | `#061e36` | Car body |
| `--b-flow-line` | `#4d7194` | Car outlines |
| `--b-flow-glass` | `#0b2d4d` | Windows, mirrors |
| `--b-flow-shell` | `#000e1c` | Battery casing, halo around figures |
| `--b-flow-tire` | `#02101d` | Tires |
| `--b-flow-rim` | `#6b8aa8` | Rims |
| `--b-flow-track` | `rgba(107, 138, 168, .3)` | Idle flow path |
| `--b-flow-text` | `#e7f1f9` | Flow text |

### Shapes

| Variable | ioDek | Role |
| --- | --- | --- |
| `--b-radius` | adaptive (14 to 24 px) | Card radius, 0 to 32 px |
| `--b-gap` | adaptive (8 to 18 px) | Spacing between cards, 4 to 32 px |

Fixed whatever the theme: the EDF Tempo colors (blue, white, red), the logo, the map (OpenStreetMap) and the headlights of the car drawing.

## Importing a theme

1. On `iodek.fr/bord`, tap the pencil **Edit layout**.
2. Tap **Theme**, then **Import a CSS file**: choose the `.css` file, or paste its content and tap **Import**.
3. The dashboard changes right away. Tap **Close**, then **Done** to save it.

The theme is saved with the layout of the screen profile (Car, or Phone or computer). In the Tesla browser, choosing a file is not always possible: paste the content, or import the theme from a computer with the **Car** profile.

**Export my theme as CSS**, in the same panel, downloads the current theme in the same format: a starting point for a new theme.

## Checking a file

With PHP 8.1 or later:

```sh
php scripts/check-theme.php themes/my-theme.css
```

The script applies the rules of the ioDek server (same code, `scripts/ThemeParser.php`, and same list, `scripts/themes.json`). It prints each problem with its line and exits with an error if a file is refused. The same check runs on every pull request (`.github/workflows/check-themes.yml`).

## Submitting a theme

1. Fork this repository and create a branch.
2. Add your file to `themes/`, named in lowercase with dashes (`themes/desert-sand.css`), starting with the `/* ioDek theme: Name */` comment.
3. Check it: `php scripts/check-theme.php themes/desert-sand.css`.
4. Open a pull request with a screenshot of the dashboard (Tesla screen or a 1180 × 800 window) and, if possible, one in bright sunlight for a light theme.

A good theme keeps text readable (contrast of at least 4.5:1 between `--b-text` and `--b-tile`), tells the accent from the alert and stays comfortable at night in the car.
