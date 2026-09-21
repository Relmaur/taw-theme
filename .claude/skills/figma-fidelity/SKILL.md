---
name: figma-fidelity
owner: taw
description: >
    The standing procedure for making a built TAW page or block match its Figma file *exactly* —
    sizing, spacing, text wrapping, radii, colours, image crops, on desktop AND mobile frames — and
    for proving it with numbers instead of by eye. Covers the gotchas that recur on every site:
    unlayered critical.scss beating Tailwind utilities, shared fluid spacing classes never equalling
    Figma's literal values, Figma stretching photos instead of cropping them, a remapped @theme
    scale, greedy text wrapping, and Form/gradient_text limits. Use after `figma-to-block` /
    `build-page` when the result "doesn't look like the design", or for "match Figma", "adapt
    <section> to the design", "sizing/spacing doesn't match", "compare this page with Figma".
    Read this BEFORE touching a block's markup or a section's spacing to close a Figma gap.
argument-hint: "<figma.com URL or section/page name to match>"
---

## Overview

`figma-to-block` gets a section from a Figma node to a working block; `build-page` assembles the sections. Neither guarantees the *rendered result* equals the design — the reference React/Tailwind output is a translation aid, and the theme's own shared utilities, critical CSS, and framework markup each silently disagree with Figma in predictable ways. This skill is the fidelity pass: measure the live DOM against Figma's own numbers, close the gaps, re-measure.

Image crop/fit problems have their own dedicated procedure — `figma-image-crop-fix` — this skill tells you *when* to reach for it.

## Ground rules

1. **Figma is the single source of truth.** If the site and Figma disagree, the site changes. Don't "improve" a design, and don't round Figma's numbers to something tidier.
2. **Pull BOTH the desktop and the mobile frames, every time.** Mobile is routinely a *different design*, not a reflow: different alignment (e.g. a centred hero with a top rule instead of a left rule), different order (form before copy), a stacked image with its own crop, no in-hero CTA because a sticky bar replaces it. Read each frame's own `get_design_context` (see `figma-to-block` Step 2) — never derive mobile from desktop.
3. **Verify with numbers, not by eye.** Compare Figma's `get_metadata` sizes/positions to the live DOM's `getBoundingClientRect()`; for images, pixel-diff (below). "Looks close" is not a result.
4. **Content writes still need a dry-run + confirmation** (`populate-content`, `AGENTS.md` § "Content-writing safety model"). Template/CSS work doesn't. If closing a gap needs a data change (e.g. re-splitting a heading's `gradient_text` segments), dry-run it, show old → new, and wait.
5. **Report drift you saw but did not change.** Fidelity passes always turn up adjacent mismatches (footer padding, a label's capitalisation, a nav offset). List them with numbers; don't silently fix things outside the request, and don't silently ignore them either.
6. Temp files (Figma screenshots, downloaded assets, diff scripts) go in the session scratchpad, never the project root.

## Procedure

1. **`get_metadata`** on the page/section node → child frame sizes and positions. This is cheap and gives the exact numbers you'll verify against. Large pages spill to a file — grep it.
2. **`get_design_context`** on the **desktop** node and its **mobile** sibling. Read the numbers and classes; write PHP + Tailwind in the block's `index.php`. Never ship JSX or `data-node-id`.
3. **Check the theme's token scale before trusting Tailwind defaults** — see "Token mapping" below.
4. Edit the block. **Run `npm run build` (or have the dev server up) after every template/SCSS edit** — stale assets cause false "it didn't work" results.
5. **Measure** the live DOM (see "Measuring in a browser"), compare against Figma's numbers, adjust, re-measure until it converges. A single end-of-work check is not the loop; measure-adjust-measure is.
6. `composer run phpstan` and `composer run test` stay green (template-only work rarely touches them).
7. **Report in numbers** — Figma vs site per element — plus the drift list from rule 5.

## Measuring in a browser

**Ask before driving a browser** (`CLAUDE.md` § "Don't", `visual-check`) — the developer often has the page open already. Once they've said yes for this task in this conversation, that yes covers the whole measure-adjust loop; don't re-ask per iteration.

Chrome DevTools MCP is the natural tool (DOM measurement, device emulation); if another session already holds its single browser profile it silently refuses a second instance — fall back to the Playwright MCP session for the same measurement.

- **Desktop:** emulate the Figma frame width, e.g. `1440x900x1`. A 15px classic scrollbar makes `clientWidth` 1425; inject `document.documentElement.style.scrollbarWidth = 'none'` to measure at exactly 1440.
- **Mobile:** emulate `375x812x2,mobile,touch`. A real desktop window can't go below ~500px, so resizing the window is not a mobile check. **Always reset the emulation back to desktop when done.**
- Use `navigate_page` with the cache ignored, then `evaluate_script` returning `getBoundingClientRect()` for every element you touched:

```js
() => { const b = e => { const r = e.getBoundingClientRect();
  return {x:+r.left.toFixed(1), y:+(r.top+scrollY).toFixed(1), w:+r.width.toFixed(1), h:+r.height.toFixed(1)}; };
  /* return { hero: b(document.querySelector('.hero')), … } */ }
```

- **Don't use `fullPage` screenshots as evidence of a bug** — lazy iframes and fixed/sticky elements get frozen or misplaced in the stitched capture. Use viewport screenshots or DOM measurements.
- For every `<img>`, `(box w/h) ÷ (natural w/h)` should be ≈ 1.00, or the image must carry `object-fit: cover`. Anything else is a stretch.
- **Also check ultra-wide (~2400px)** for any full-bleed image in a width-capped wrapper: a `transform: scale()` zoom overflows the box and, without `overflow-hidden` on the capped wrapper, escapes past the edge fades as a bright unfaded band (`figma-image-crop-fix` Step 4).

## Token mapping — check before assuming Tailwind defaults

- **Radius / spacing / colours:** look at the `@theme` block in `resources/css/app.css` (or `app.scss` / `_tokens.scss` from `build-page` Step 2) first. Themes often remap the scale to Figma's names — e.g. `rounded-xl` = 16px and `rounded-2xl` = 24px because they mirror Figma's `radius/xl`/`radius/xxl`, not Tailwind's own defaults (12/16).
- **Spacing:** Figma `gap-N` (N px) = Tailwind `N/4` (`gap-32` → `gap-8`, `pb-120` → `pb-30`) on the default 0.25rem scale.
- **Similar-looking text tokens are different tokens** (`text/secondary` vs `text/tertiary`, `border/default` vs a neutral grey). Map by Figma name, never by "close enough" hex.
- **Type styles:** if the theme has responsive type classes (`.t-h1` etc.) check they already equal Figma's per-breakpoint styles before hand-tuning sizes on a block.

## Section spacing — shared fluid classes never equal Figma's literals

A shared fluid class (`.section-y` = `clamp(64px, 10vw, 160px)`, `.section-shell` = `clamp(1rem, 5.5vw, 160px)` side padding, or similar) is a generic approximation. It will **not** reproduce Figma's literal 160 / 80 / 64px at the reference width (e.g. it lands on 144px / ~79px at 1440) — and Figma itself varies margins per section (wide 32px card grids vs 160px "content" sections).

- For a section that must match exactly, set **explicit per-section padding** (`py-16 md:py-40`) and **cap the content column** (`mx-auto w-full max-w-[1120px]`) instead of relying on the shared class.
- **Never edit the shared class to fix one section** — it shifts every other section's rhythm.
- The same drift silently breaks image crops (a card that's a different shape than Figma computed its percentages against) — see `figma-image-crop-fix`.

## `critical.scss` is unlayered — it beats Tailwind utilities

`resources/scss/critical.scss` is hand-authored, inlined into `<head>`, and **not** inside a Tailwind `@layer`. Unlayered rules beat layered utilities regardless of specificity or source order, so a `pb-0` / `md:max-w-*` utility on an element that `critical.scss` also styles (typically `.hero__inner` padding/max-width) is **silently ignored**. Symptoms: you edit the utility, rebuild, and nothing moves.

- Vary an above-the-fold block per page / per state with a **modifier class defined in `critical.scss`** (`.hero__inner--inner-page`, `.hero__inner--with-image`), toggled from the template — not with utilities.
- Keep any class shared between `critical.scss` and `app.css` (e.g. `.section-shell`) identical in both.
- A stale rule in `critical.scss` is inlined in production and overrides your utilities there too — when a block's classes change, revisit its critical rules (`AGENTS.md` § "CSS entry points").

## Images

Work through this triage per image, using the specific image node's own `get_design_context` (not its parent frame):

| Generated `<img>` shows… | Meaning | Reproduce with |
|---|---|---|
| `object-cover`, no offsets | plain crop | `Image::render()` + `object-cover` (already the default) |
| `absolute h-[X%] w-[Y%] left-[-A%] top-[-B%]` in an `overflow-hidden` box | Figma pan/zoom transform | the verbatim transform **only** in an aspect-locked box; otherwise `object-cover` + zoom + `object-position` |
| `size-full` (often `object-bottom`), **no** `object-cover`, no offsets | **stretch — Figma's `object-fit: fill`** | `object-fill` inside an `aspect-[W/H]`-locked box |

Details, formulas and the container-shape gotchas are in `figma-image-crop-fix`. Two things belong here because they're diagnostics, not recipes:

- **The uploaded file is the same file Figma uses** (same pixel dimensions). A mismatch is a crop/fit problem, not a wrong asset — don't re-export or re-edit the image.
- **Pixel-diff when a crop is in question.** Download Figma's `get_screenshot` (large `maxDimension`) into the scratchpad, crop it to the image's box, render the source under each hypothesis (stretch; cover scaled to width at every vertical offset; zoom candidates via `cv2.matchTemplate`), and take the mean absolute difference. The true mapping scores in single digits, wrong ones 20+ (a real case: stretch scored 2.4, every cover offset ≥ 25). Diff only the photo region, not the text, so anti-aliasing doesn't pollute the score. Write the script in the scratchpad; don't add a project dependency.

**Percent transforms vs `object-position`:** when a Figma transform can't be applied verbatim (fluid/content-height container) the equivalent for a top-left-anchored zoom is: `zoom = Figma image width% ÷ 100`, `object-position-y = (|top%| × frame height ÷ zoom) ÷ (cover height − frame height)` — assumes Figma's `left ≈ 0`; a horizontal shift as well means you need an aspect-locked box instead. Comment the derivation next to the numbers in the template, and note they are tied to *that specific uploaded image*.

## Text

- **Figma wraps greedily inside a fixed-width text box.** Do not use `text-balance`; give the heading Figma's box width (`max-w-[672px]`) and let it wrap naturally. If a phrase must wrap as a unit, wrap it in an `inline-block` span.
- **Trailing punctuation stays outside a gradient** when the design shows it plain: split it into its own non-highlighted `gradient_text` segment (`How can ` / `we help`(highlighted) / `?`). That is a content write — dry-run first (rule 4).
- **`gradient_text` cannot store a hard line break** — it strips newlines, so a Figma `<br>` (common in mobile headings) can't be saved. Emulate with layout (an `inline-block` highlight span, or a width cap that forces the same wrap) and mention the limitation in your report. (A `textarea` field split on `\n` is the right shape for a `<br>` that's genuinely editor-controlled — see `figma-to-block` Step 4.)

## Forms

`Form::register()` output and its `.taw-*` classes are framework CSS: 15px labels, 40px inputs, a full-width gradient submit button. To match a Figma form:

- Override **inside the block's own scope only** so other forms on the site keep the framework default. On `taw/core` ≥ v1.40.0 give the form its own hook — `Form::register(['class' => 'contact-form', 'button_class' => 'contact-cta', …])` — and scope overrides to `.contact-form .taw-input`, `.contact-form .contact-cta` (both options *add to* the framework classes).
- Per-field width is the `--taw-span` custom property (≥ v1.40.0), not an inline `grid-column`, so a mobile override is an ordinary rule — `.contact-form .taw-form-field { grid-column: 1 / -1; }` — no `!important`.
- **On `taw/core` < v1.40.0** neither hook exists: scope under a block wrapper (`.contact .taw-form .taw-input`), the submit button is hard-coded `taw-btn taw-btn-primary`, and the inline `grid-column: span N` needs `!important` in a media query. Prefer updating `taw/core` (ask first — `update-theme`) over living with the workaround.
- `submit_icon` is optional — check whether the design shows an icon on the CTA at all before setting it.

## Mobile ≠ desktop — things that have actually differed

Form-first order; fields stacking full-width with a CTA that stretches to the field width (desktop: hugs its label, right-aligned); a centred hero with a *top* rule where desktop has a *left* rule; no in-hero CTA (a sticky bar instead); image stacked above a panel that sits over it on desktop; smaller chips/connectors; a completely different image crop and box shape. None of these can be predicted from the desktop frame — which is why rule 2 exists.

## Don't

- Don't verify by eye or with a `fullPage` screenshot and call it matched — measure, and quote numbers.
- Don't edit a shared utility (`.section-y`, `.section-shell`, a framework `.taw-*` class) to fix one section.
- Don't apply a `critical.scss`-styled element's spacing with utilities — use a modifier class in `critical.scss`.
- Don't reuse a desktop image crop or layout on mobile; pull the mobile frame.
- Don't assume Tailwind's stock radius/spacing scale — read the theme's `@theme` first.
- Don't drive a browser without the developer's yes, and don't leave the device emulation on when you finish.
- Don't fix drift you weren't asked about — list it.
