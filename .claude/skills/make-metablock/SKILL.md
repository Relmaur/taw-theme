---
name: make-metablock
description: >
    Scaffolds and fully implements a new TAW MetaBlock (or presentational Block) end-to-end —
    class, metabox fields, template, and optional styles — from a plain-language description
    of a page section (e.g. "a pricing table with 3 tiers" or "a testimonial carousel").
argument-hint: "<section description, e.g. 'hero with heading, tagline, background image, two CTAs'>"
---

## Overview

This skill turns a short description of a page section into a working TAW block: the PHP class, its metabox field config, the render template, and (if needed) styles — following the exact conventions in `AGENTS.md`. Use it any time the user asks to add a new section/block, not just when they say "metablock" literally.

**Source of truth:** `AGENTS.md` in the repo root — specifically the "Common Section Catalog" (canonical field configs for Hero, Features, Testimonials, Pricing Table, CTA, Team, FAQ, Contact, Stats, Logo Bar) and "Creating a New MetaBlock" sections. Read those before generating fields for a new block. When in doubt about a framework API (field type option, `Metabox` config key), check the `mcp__taw-docs__search_documentation` MCP tool first if available (fastest, always current), otherwise fetch the `taw/core` README (https://github.com/Relmaur/taw-core#readme) — either wins over anything cached here.

## Step 1 — Decide MetaBlock vs Block

| The section… | Use |
|---|---|
| Owns its own content, editable per-page in WP Admin | **MetaBlock** (has metaboxes) |
| Is a reusable UI primitive with no admin-editable content (button, card, badge) rendered with props from a parent | **Block** (presentational) |
| Needs a form with email delivery | MetaBlock that registers a `Form` in `boot()` and calls `Form::display()` in its template |

If unsure, default to MetaBlock — it's the far more common case for named page sections.

## Step 2 — Check for an existing block first

List `Blocks/` and compare against the request. If an existing block already covers the section (even under a different name), reuse/queue it instead of creating a duplicate — ask the user only if the match is ambiguous.

**Before committing to reuse, skim the candidate block's field list** (`registerMetaboxes()`). Dev/demo blocks in this repo can accumulate unrelated fields from earlier scaffolding experiments (e.g. `Blocks/Hero` also carries `team_members`, `featured_post`, `related_posts`, and nested-repeater demo fields that have nothing to do with a hero banner). Reusing such a block is still usually right — it's cheaper than a duplicate and the extra fields are harmless if unused in your template — but call it out to the user briefly ("reusing Hero, note it also has some unrelated demo fields in its metabox you can ignore") rather than silently reusing a block whose admin UI is cluttered.

## Step 3 — Match against the Common Section Catalog

If the requested section matches (or closely resembles) one of the catalog entries in `AGENTS.md` § "Common Section Catalog" (Hero, Features/Services, Testimonials, Pricing Table, CTA, Team, FAQ, Contact, Stats/Numbers, Logo Bar/Partners), start from that field config verbatim and adapt labels/counts to the request. Don't reinvent field structure for a section type the catalog already covers.

For anything not in the catalog, design fields from scratch using the same idioms:
- Simple text → `text` / `textarea` (with `rows`)
- Repeated sub-items (cards, list rows, slides) → `repeater` with nested `fields`
- Images → `image` (single) or `files` (multiple, ordered)
- Buttons/links → paired `text` + `url` fields, or a `group` if there's a natural cluster (e.g. social links)
- On/off toggles → `checkbox`
- Related content lookups → `post_select`
- Use `width` (percentage, e.g. `'50'`, `'25'`, `'33'`) to lay fields out side-by-side in the admin instead of always stacking full-width
- Use `tabs` if the block ends up with more than ~8 top-level fields, grouping by concern (e.g. Content / Media / CTA)

Full field type list and options: `AGENTS.md` § "The Metabox Framework", or the `taw/core` README § Metabox System.

## Step 4 — Scaffold

Prefer the CLI over hand-writing boilerplate:

```bash
php bin/taw make:block SectionName --type=meta --with-style
composer dump-autoload
```

- `SectionName` must be **PascalCase** and become both the folder name and the class name (`Blocks/SectionName/SectionName.php`) — this must match exactly, auto-discovery breaks otherwise.
- Add `--group=sections` (or another subgroup) to scaffold inside `Blocks/sections/SectionName/` for larger projects that organize blocks into subfolders.
- Omit `--with-style` if the section clearly needs no bespoke CSS (rare — default to including it).
- If the CLI is unavailable for some reason, create the two files manually per `AGENTS.md` § "Creating a New MetaBlock" — same structure, same naming rule.

## Step 5 — Fill in the class

Edit `Blocks/SectionName/SectionName.php`:

1. Set `protected string $id` to a short snake_case id (defaults to the CLI output — keep it unless there's a collision).
2. In `registerMetaboxes()`, build the `Metabox` config from Step 3 — `id` (prefixed, e.g. `taw_section_name`), `title` (admin-facing label), `screens` (which post types/templates it can attach to — ask the user if unclear, default to `['page']`), and `fields`.
3. In `getData(int|false $postId)`, fetch every field via `$this->getMeta($postId, 'field_id')` (or `Metabox::get_repeater()` / `::get_image_url()` / `::get_posts()` for the relevant field types) and return a flat associative array — this is what `index.php` receives via `extract()`.
   - **The signature must be exactly `getData(int|false $postId): array`** — matching `MetaBlock`'s abstract declaration. `getData(int $postId)` (missing `|false`) is a PHP fatal "incompatible declaration" error, and because `BlockLoader::loadAll()` auto-discovers and instantiates every block on every request, this single typo takes the **entire site** down, not just this block. Older CLI-scaffolded blocks in this repo have been found with this exact bug — if you touch or reuse any existing `getData()` while working, verify its signature too, don't assume it's already correct. Run `php bin/ci/check-getdata-signature.php` to check every block at once instead of eyeballing each one — the same check CI runs on every push.
4. If the section needs a form, register it in `boot()` wrapped in `add_action('init', ...)` — **never** in the template or in `getData()`. See `AGENTS.md` § "Contact" catalog entry for the exact pattern.
5. If the block reads from `OptionsPage::get('some_field')` (e.g. company phone/email/address), verify that field is actually registered in `inc/options.php`. `OptionsPage::get()` on an unregistered field silently returns empty instead of erroring — don't assume a field exists just because another block already calls it; grep `inc/options.php` for the field id and add it (plus a `tabs` entry) if missing.

## Step 6 — Build the template

Edit `Blocks/SectionName/index.php`:

- Declare the expected variables with `/** @var */` doc comments at the top for readability.
- Guard against empty required content (`if (empty($heading)) return;`) when the section shouldn't render without it.
- Always escape output: `esc_html()`, `esc_url()`, `esc_attr()`, `wp_kses_post()` for the wysiwyg/html fields.
- For repeaters, `json_decode()` the value (or use `Metabox::get_repeater()` which already does this) and loop with `foreach`.
- Use Tailwind utility classes for layout/styling, matching the visual conventions of neighboring blocks in `Blocks/` — check 1–2 existing block templates for the project's spacing/typography scale before writing markup from scratch.
- If the section renders a `Form`, call `Form::display('form_id')` here.

## Step 7 — Styles (if scaffolded with `--with-style`)

Edit `Blocks/SectionName/style.scss` (preferred) or `style.css`. Both are auto-discovered and auto-enqueued — never manually `wp_enqueue_style()`. Keep block-specific styles here rather than in global `resources/scss/app.scss` unless the pattern is shared across many blocks.

**If the template ends up entirely Tailwind-utility-styled with no custom rules**, delete the empty scaffolded `style.scss` stub rather than leaving a dead file with an empty selector — an unused stylesheet is dead weight, not a placeholder worth keeping "just in case."

## Step 8 — Wire it into a page

If the user also wants this section placed on a page, hand off to the page-assembly workflow: queue it with `BlockRegistry::queue('section_name', ...)` before `get_header()` and render it with `BlockRegistry::render('section_name')` in the right position in the page template. See the `build-page` skill or `AGENTS.md` § "Building a Page — AI Playbook" for full template conventions.

## Step 9 — Verify

- Confirm the folder name, class name, and `$id` all agree.
- Run `composer dump-autoload` if it wasn't already run by the CLI.
- `php -l` every PHP file you touched or created.
- Run `php bin/ci/check-getdata-signature.php` — same check CI runs, catches the signature bug across every block in one shot, not just the one you just wrote.
- **Check the site actually loads** (e.g. `curl -sL -o /dev/null -w '%{http_code}' <site-url>`), not just that this one block's files are syntactically valid. Because every block auto-loads on every request, a signature mismatch (see Step 5.3) or fatal anywhere in `Blocks/` — including in files you didn't touch — takes the whole site down, and that failure mode won't show up from linting your new files alone.
- Optionally, `php bin/taw inspect` reports the live registered blocks/fields — a fast way to confirm the new block is actually discovered and its fields match what you intended, without opening wp-admin.
- If a dev server is running (`npm run dev`), load a page containing the block and check both the front end render and the WP Admin metabox UI (fields appear, save correctly, repeaters add/remove rows).
- Don't report the task done without at least a visual check when a browser is available — see the project's `verify` skill for the general pattern.

## Marking generated files (when explicitly requested)

If the user asks for generated files to be identifiable as skill-produced (e.g. for a demo, or to distinguish AI-authored blocks during review), add a short doc-comment block at the top of each new/rewritten PHP file naming this skill and its path, e.g.:

```php
/**
 * Generated by the `make-metablock` Claude Code skill.
 * See .claude/skills/make-metablock/SKILL.md.
 */
```

Don't add this by default — only when asked — to avoid cluttering ordinary block code with provenance comments no one asked for.

## Don't

- Don't put block classes anywhere but `Blocks/{Name}/{Name}.php` — PSR-4 only maps `TAW\Blocks\` → `Blocks/`.
- Don't register forms inside `index.php` or `getData()`.
- Don't call `wp_enqueue_style`/`wp_enqueue_script` directly for block assets.
- Don't hand-roll a field type the catalog or `taw/core` README already documents — reuse it (e.g. don't build a custom repeater-of-links when `group` + `repeater` composition already covers it).
- Don't forget `composer dump-autoload` after adding a new class file manually (the CLI does this for you).
