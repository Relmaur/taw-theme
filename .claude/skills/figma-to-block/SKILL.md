---
name: figma-to-block
description: >
    Implements a single TAW block from a Figma design node — pulls design context via the
    Figma MCP tools and converts the reference React+Tailwind output into a PHP MetaBlock
    (class, metabox fields, PHP template) that matches the target visual exactly. Use for
    "implement this design/section/frame from Figma" requests with a figma.com URL.
argument-hint: "<figma.com/design/... URL with a node-id> [+ optional section description]"
---

## Overview

This is `make-metablock`, but the field shape and visual spec come from a Figma node instead of a plain-language description. Read `.claude/skills/make-metablock/SKILL.md` first — every convention there (MetaBlock vs Block decision, naming, `getData()` signature, verify steps) still applies. This skill only adds the Figma-specific extraction and conversion steps in front of it.

**Source of truth for the visual spec:** the Figma file itself, via the MCP tools below — not assumptions or memory of similar designs. **Source of truth for framework conventions:** `AGENTS.md`, same as `make-metablock`. For framework API questions, prefer `mcp__taw-docs__search_documentation` (if available) over fetching docs by hand.

## Step 1 — Resolve the Figma URL

Extract `fileKey` and `nodeId` from the URL: `https://figma.com/design/:fileKey/:fileName?node-id=1-111` → `fileKey = :fileKey`, `nodeId = 1:111` (hyphen becomes colon). If the URL has no `node-id`, ask the user for a node-specific link — don't guess a node id.

## Step 2 — Get an overview before pulling full context

For a single, already-scoped node, you can skip straight to Step 3. But if the request is ambiguous about scope (a whole page URL, or "implement the homepage"), call `get_metadata` first — omit `nodeId` to list top-level pages, or pass a page/frame id to see its child frames (names, ids, dimensions) without pulling full code. Use this to identify which child frame(s) actually correspond to "sections" (in this codebase's designs, top-level section frames are typically named `Section - <Roman numeral>. <Name>` or similar — read the names, don't guess boundaries from position alone).

**Large pages can exceed the tool's token limit** — `get_metadata` on a big page node will save its output to a file and tell you to read it in chunks; when that happens, grep/search the saved file for `<frame` tags and section-like names rather than reading it all sequentially, unless you actually need the full tree.

## Step 3 — Pull design context for the target node

```
get_design_context(fileKey, nodeId, clientLanguages="php,html,css,javascript", clientFrameworks="wordpress,tailwindcss,alpinejs")
```

This returns three things — use all three:
- **Reference code** — React + Tailwind. This is a translation aid, not the output. It tells you exact classes/values (colors, sizes, spacing) Figma computed, which is far more reliable than eyeballing the screenshot for pixel values.
- **Screenshot** — visual ground truth. Cross-check the reference code against it (text content, layout, what's visible vs decorative).
- **Asset download URLs** — for any images/SVGs in the node, valid for 7 days. If the design includes real images (this may not always be the case — a text-only hero has none), download them and either commit them into `resources/` (for a fixed/theme asset) or note that the user needs to upload them via WP Media Library and select them through the block's `image` metabox field, since a MetaBox `image` field always stores a WordPress attachment ID, not a static path.

**Do not ship React or JSX.** The critical conversion step is translating the reference code's structure and Tailwind classes into a PHP template, not copy-pasting it. Preserve every color, size, spacing, and text value; discard React-specific syntax (`className` → `class`, `data-node-id` attributes → drop them, component functions → plain markup).

## Step 4 — Decide MetaBlock structure from the design

Walk the reference code's text/image nodes and classify each into a field, same idioms as `make-metablock` Step 3:
- Distinct text runs the client will want to edit later (heading, eyebrow/label, body copy, button labels) → metabox fields (`text`/`textarea`)
- Multi-line headings with **manual line breaks that look intentional** (not just wrapping) → a single `textarea` field, one line per row, rendered by splitting on `\n` and joining with `<br>` — don't hardcode the line breaks in the template, an editor changing the heading later needs to control them too
- Images → `image` field
- Repeated similar elements (cards, list items, logos) → `repeater`, same as any other catalog section
- Buttons/links → paired `text` + `url` fields per button

**Check for an existing block to reuse before creating a new one** (same as `make-metablock` Step 2) — but for Figma-sourced work, reuse must match **visually**, not just semantically. A block named "Hero" with the right field shape but a completely different visual style (different layout, palette, or typography) is not a real match — creating a new, distinctly-named block is correct in that case. Say so explicitly rather than silently reusing or silently duplicating.

## Step 5 — Scaffold and implement

Follow `make-metablock` Steps 4–7 exactly (CLI scaffold, PascalCase naming, `registerMetaboxes()`, `getData(int|false $postId): array` — **the signature bug is just as fatal here**, delete the empty `style.scss` stub if everything ends up Tailwind-only, etc).

Additional Figma-specific rules for the template:

- **Colors/sizes from the design that aren't already project design tokens** (check `resources/css/app.css` for a Tailwind `@theme` block, and `_fonts.scss`/`resources/fonts/` for existing type) should be applied as Tailwind arbitrary values (`bg-[#12212b]`, `text-[64px]`) rather than invented utility/theme names — don't silently add global `@theme` tokens for a one-off section. Mention to the user that promoting a color/font to a shared token is worth doing if the same design system recurs across sections.
- **Fonts:** if the design specifies a typeface not already self-hosted in `resources/fonts/`, don't block on it — fall back to the closest Tailwind generic stack (`font-serif` / `font-sans` / `font-mono`) and leave a comment noting the exact intended typeface and pointing at `AGENTS.md`'s font-loading convention (`resources/fonts/` + `_fonts.scss` + preload hint) for the user to add it for pixel-exact results. Ask if they want you to source and wire up the webfont now instead.
- **Don't force-fit an existing shared component** (e.g. a generic `Button` block) whose current styling doesn't match the design — hand-code the markup for this section rather than distorting a shared component's visuals for one caller, unless the user asks you to update the shared component itself.

## Step 6 — Ask about content population

**If `build-page` invoked this skill and already supplied a population answer for the whole page, use that instead of asking again** — don't re-ask per section when the page-level decision already covers it.

Once the block is wired into a page and a target post exists, ask the user which they want:

1. **Populate real values extracted from the Figma design** — the exact text runs pulled in Step 3, via `populate-content`. This is a real content write — `populate-content` applies the full confirmation/dry-run safety model from `AGENTS.md` § "Content-writing safety model"; the fact that the copy came from an approved design doesn't skip that.
2. **Leave fields empty and rely on template fallbacks** — verify the template actually renders sensibly when empty rather than showing visibly broken markup.
3. **Fill with Lorem Ipsum placeholder content** — also a real write via `populate-content`, but generic placeholder text/rows shaped like the real fields (matching repeater row counts) rather than the design's actual copy.

If there's no target post yet, say so and defer this question until the block is actually wired into a page with a real post behind it.

## Step 7 — Verify

Standard `make-metablock` verify steps apply (site loads, `php -l`, `composer dump-autoload`), plus, since real data won't exist in the DB yet for a brand-new block:

- **Render the template standalone** to check markup/escaping/data-flow without needing WordPress or a browser: write a throwaway PHP script that stubs the handful of WP functions the template calls (`esc_html`, `esc_url`, `esc_attr`, `esc_html_e`, `esc_attr_e`, `wp_kses_post` — pass-through stubs are fine for this purpose), sets variables matching the design's actual copy, and `require`s the template file directly. This catches PHP errors, missing-variable warnings, and escaping bugs immediately, in the scratchpad directory, without touching any real template.
- **Cross-check Tailwind values by hand** against the Figma spec if you can't compile the actual stylesheet (this project's Tailwind v4 only builds through Vite, no standalone CLI) — Tailwind's default spacing scale is `n × 0.25rem`, so e.g. Figma's `px-[80px]` ⇔ project's `px-20` (20 × 4px = 80px). Do this arithmetic rather than asserting a match you haven't checked.
- If a dev server (`npm run dev`) is available, offer (don't auto-run) the `visual-check` skill for a true pixel check — it drives a real browser via the Playwright MCP tools to screenshot the rendered section and compares it against the `get_screenshot` output. Offer this explicitly rather than assuming the standalone render + hand-check is sufficient when the user needs pixel accuracy; only run it on an explicit yes.
- Don't wire the new block into a live page template as part of "verification" without telling the user — if you need to temporarily queue/render it somewhere to test, revert the change afterward and say you did so.

## Don't

- Don't ship React/JSX or literal Figma class names (`data-node-id`, etc.) into the PHP template — full conversion, not a copy-paste.
- Don't invent global theme tokens for one-off design colors/fonts without flagging it to the user first.
- Don't silently reuse a same-purpose block whose visuals don't actually match the design.
- Don't skip `make-metablock`'s `getData(int|false $postId): array` signature check — it's the same site-wide-fatal risk here.
- Don't leave temporary test wiring (queue/render calls added purely to verify) in a page template — revert it after checking.
- Don't populate real content from the design without asking the three-way question in Step 6 first, and don't skip `populate-content`'s dry-run/confirmation safety model just because the content came from an approved design.
