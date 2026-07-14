---
name: build-page
description: >
    Assembles a full WordPress page template out of TAW blocks from either a plain-language
    brief (e.g. "homepage with hero, features, testimonials, pricing, and a contact form"), a
    Figma page/frame URL (e.g. "build this page from this Figma design"), or a pasted/attached
    screenshot. Reuses existing blocks where possible, creates missing ones, wires up the page
    template with correct queue/render ordering, and — for a design-sourced brief — asks once
    whether to populate real content, leave fallbacks, or use Lorem Ipsum.
argument-hint: "<page description OR figma.com/design/... URL OR screenshot covering multiple sections>"
---

## Overview

This skill fulfills requests like *"I need a homepage with a hero, features, and a contact section"* — the exact scenario `AGENTS.md` § "Building a Page — AI Playbook" is written for — **and** requests like *"build this page from this Figma design"* or *"build this page from this screenshot"* where a design is the source of truth instead of a text description. It orchestrates section-by-section decisions and produces a working page template, delegating individual block creation to `make-metablock` (text-brief or screenshot sections) or `figma-to-block` (Figma-sourced sections), and content population to `populate-content`.

**Source of truth:** `AGENTS.md` § "Building a Page — AI Playbook" and § "Common Section Catalog" for the text-brief path. The Figma file itself (via the MCP tools) for the design-sourced path. Read the relevant one before starting — this skill is the procedural wrapper, not a replacement for either. For framework API questions along the way, prefer `mcp__taw-docs__search_documentation` (if available) over fetching docs by hand.

## Step 1 — Determine the brief type and break it into named sections

**Text brief:** Parse the user's request into an ordered list of sections (top to bottom, matching the page's visual flow). If vague ("a homepage"), propose a sensible default order (e.g. Hero → Features → Testimonials → Pricing → FAQ → CTA) and confirm before building, unless already specified.

**Figma brief:** If given a `figma.com` URL, extract `fileKey`/`nodeId` and call `get_metadata` on the target node (or omit `nodeId` to list top-level pages first, if the URL points at the whole file rather than one page). Read the child frame names to identify section boundaries — in Figma files built for this kind of work, top-level section frames are typically named descriptively (e.g. `Section - II. Value Proposition`); use those names and their rendering order as your section list. Don't call `get_design_context` on every section yet — that happens per-section inside `figma-to-block` in Step 2. If the file contains multiple alternative page proposals (e.g. several full homepage variants), confirm with the user which one to build before proceeding.

**Screenshot brief:** Same as a Figma brief, but reading section boundaries visually from a pasted/attached image instead of Figma's frame tree — identify distinct visual sections top to bottom and treat each as a section, same as above.

**If this is a Figma or screenshot brief, ask the content-population question once, for the whole page, right here** — don't let each section ask it separately later: populate real extracted content, leave fields as fallbacks, or fill with Lorem Ipsum. Carry the answer through Step 2 so `make-metablock`/`figma-to-block` don't re-ask it per section.

## Step 2 — Resolve each section against existing blocks

For every section in the list:

1. Check `Blocks/` for an existing block that already covers it (by name or purpose against `AGENTS.md`'s catalog descriptions). **For a Figma brief, a name/purpose match isn't enough — the existing block's actual visual style must match the design too** (layout, palette, typography); a same-purpose block with different visuals is not a real match. See `figma-to-block`'s Step 4 for this same rule applied at the block level.
2. If found, reuse it as-is — do not recreate.
3. If not found:
   - **Text brief** → invoke **`make-metablock`** for that section, passing its description.
   - **Figma/screenshot brief** → invoke **`figma-to-block`** (or `make-metablock` for a screenshot) for that section, passing the section frame's `fileKey`/`nodeId` (or the relevant crop of the screenshot) **and the population answer from Step 1** — tell it not to ask its own per-block population question, since this skill already asked once for the whole page.
   Batch this: identify *all* missing blocks first, then create them, rather than interleaving discovery and creation section-by-section.

Keep a running map of `section name → block id` (the `$id` property, used for `queue()`/`render()`) as you go — you'll need it for the template.

## Step 3 — Determine the target template file

Ask (or infer from context) which page this is for, then pick the file per WordPress's template hierarchy:

| Template file | When it loads |
|---|---|
| `front-page.php` | The site's static front page |
| `page-{slug}.php` | Page with that specific slug (e.g. `page-about.php`) |
| `page-{id}.php` | Page with that specific post ID |
| `page.php` | All other pages (fallback) |

If the target file already exists, read it first — you're likely inserting/reordering sections in an existing template, not starting from scratch. Preserve any surrounding markup (wrappers, conditional sections) that isn't part of this request.

**Slug/ID-matched templates (`page-{slug}.php`, `page-{id}.php`, `front-page.php`) apply automatically — they never appear in the block editor's "Template" dropdown**, and this project has no template using a `Template Name:` header (which is the only kind that *does* show up there). Don't expect or ask the user to manually select the template from a dropdown for these; the fix is simply assigning the matching slug (or post ID) to the Page in WP Admin. If the user specifically wants a template selectable regardless of slug, add a `Template Name: X` header comment instead — flag that this deviates from every other template in this repo before doing it.

## Step 4 — Write the template

Follow the exact skeleton from `AGENTS.md`:

```php
<?php
// page-example.php

use TAW\Core\Block\BlockRegistry;

// 1. Queue all blocks BEFORE get_header() so assets land in <head>
BlockRegistry::queue('hero', 'features', 'testimonials', 'cta');

get_header();
?>

<?php BlockRegistry::render('hero'); ?>
<?php BlockRegistry::render('features'); ?>
<?php BlockRegistry::render('testimonials'); ?>
<?php BlockRegistry::render('cta'); ?>

<?php get_footer();
```

Rules:
- **Every** block id used in `render()` must also appear in the `queue()` call, and `queue()` must run before `get_header()`. This is the single most common mistake — double-check it before finishing.
- The `render()` call order determines both visual order on the page and, if `MetaboxOrder::lockFromTemplate()` is active (see `AGENTS.md` § "The Metabox Framework" → "Locking Metabox Order"), the metabox order in wp-admin — so section order in the template is not cosmetic, it drives the editing UI too.
- Don't add manual `wp_enqueue_style`/`wp_enqueue_script` calls — blocks self-enqueue via the queue/render pattern.

## Step 5 — Populate content (Figma/screenshot brief only)

Skip this step entirely for a text brief with no design-sourced content to populate.

If Step 1's population answer was "populate real values" or "Lorem Ipsum", resolve the actual target WP post now:

- If the Page post this template will render for already exists (matching slug/ID per Step 3), use it.
- If it doesn't exist yet, tell the user a Page post needs to be created before content can be populated (this skill writes the *template*, not page content) and ask whether to create it now (`wp post create` or similar) or defer population until they create it themselves.

Once a target post id exists, invoke `populate-content` **once per section**, passing the population choice from Step 1 and the copy/placeholder content each section's `make-metablock`/`figma-to-block` invocation already gathered — don't re-extract it. `populate-content` owns the actual dry-run/confirmation flow per `AGENTS.md` § "Content-writing safety model"; this skill doesn't shortcut that by batching all sections into one silent write.

## Step 6 — No functions.php changes

`BlockLoader::loadAll()` auto-discovers every block in `Blocks/*/`. Never manually register blocks in `functions.php`. The only thing that might belong in `functions.php` is `MetaboxOrder::lockFromTemplate()` (already present in this project) — don't touch it unless asked.

## Step 7 — Verify

- Confirm every queued block id matches an actual `$id` property in `Blocks/*/*.php`. `php bin/taw inspect` reports the live registered block ids/fields if you want to double-check without reading source.
- Run `php bin/ci/check-getdata-signature.php` — same check CI runs, catches the `getData(int|false $postId)` signature bug across every block in the project in one shot.
- **Check the site loads at all before checking anything page-specific** (e.g. `curl -sL -o /dev/null -w '%{http_code}' <site-url>/`). `BlockLoader::loadAll()` auto-discovers and instantiates *every* block in `Blocks/*/` on *every* request, so a fatal error in any single block file — including ones you didn't touch this session — takes the entire site down, not just the page you built. If you get a 500/503, check `wp-content/debug.log` before assuming the problem is in your new code; it may be pre-existing breakage you've just now exposed by loading a page for the first time since it broke.
- If a dev server is running, load the actual page and check section order and rendering top to bottom, plus that new blocks' metaboxes appear correctly in wp-admin.
- **For a Figma/screenshot brief, once the full page is wired up, offer (don't auto-run) a pixel-accuracy pass via the `visual-check` skill** — a real browser screenshot of the finished page compared against the design reference. This is a separate opt-in step the developer decides on, not part of this checklist by default.
- Report back the final section list and which blocks were reused vs newly created.
- A 404 on the intended URL after the template file is written correctly usually means the Page post itself doesn't exist yet with the matching slug — that's expected, it's a content-authoring step, not a bug in the template. See the note in Step 3 about slug-matched templates never needing manual selection.

## Marking generated files (when explicitly requested)

If asked to make the generated page identifiable as skill-produced, add a short doc-comment at the top of the template file naming this skill (and `make-metablock`, if it created any blocks), e.g.:

```php
/**
 * Generated by the `build-page` Claude Code skill (which delegated block
 * creation to `make-metablock`). See .claude/skills/build-page/SKILL.md.
 */
```

Don't add this by default — only when asked.

## Don't

- Don't create a new block for a section that already exists under a different name without checking first.
- Don't reorder existing, unrelated sections in a template the user didn't ask you to touch.
- Don't skip `make-metablock`'s or `figma-to-block`'s own conventions (naming, field catalog, escaping, signature checks) when creating missing blocks — this skill delegates block creation, it doesn't duplicate that logic.
- Don't fix unrelated fatal errors you discover in `Blocks/*/*.php` while verifying without telling the user first — flag it and get confirmation before touching code outside the scope of the request (see Step 7), even though the bug may be blocking your own verification.
- Don't skip the page-level population question (Step 1) for a Figma/screenshot brief, and don't let `populate-content`'s per-section confirmation gates get silently batched away — every section's write still needs its own dry-run/confirmation per `AGENTS.md`'s safety model.
- For a Figma brief, don't skip straight to full `get_design_context` calls for every section up front — get the section list cheaply via `get_metadata` first, confirm scope with the user if the file has multiple page variants, then pull full context per-section only once you're actually building it.
