---
name: make-metablock
description: >
    Scaffolds and fully implements a new TAW MetaBlock (or presentational Block) end-to-end —
    class, metabox fields, template, and optional styles — from a plain-language description
    of a page section (e.g. "a pricing table with 3 tiers" or "a testimonial carousel"), or from
    a pasted/attached screenshot of a reference design.
argument-hint: "<section description, e.g. 'hero with heading, tagline, background image, two CTAs'> [+ optional screenshot]"
---

## Overview

This skill turns a short description of a page section — or a pasted/attached screenshot of one — into a working TAW block: the PHP class, its metabox field config, the render template, and (if needed) styles — following the exact conventions in `AGENTS.md`. Use it any time the user asks to add a new section/block, not just when they say "metablock" literally. If the user instead provides a `figma.com` URL, use `figma-to-block` instead — it pulls exact design tokens and asset URLs a screenshot can't provide.

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

**Before committing to reuse, skim the candidate block's field list** (`registerMetaboxes()`). Blocks can accumulate unrelated fields over time from earlier scaffolding experiments or scope creep — a "Hero" block that's picked up team-member or related-posts fields that have nothing to do with a hero banner, say. Reusing such a block is still usually right — it's cheaper than a duplicate and the extra fields are harmless if unused in your template — but call it out to the user briefly ("reusing Hero, note it also has some unrelated fields in its metabox you can ignore") rather than silently reusing a block whose admin UI is cluttered. The shipped `Blocks/Hero` in this scaffold is intentionally minimal (matches AGENTS.md's catalog entry exactly) — treat any drift from that as the kind of accumulation this note is warning about.

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

**If a screenshot was provided instead of (or alongside) a text description**, read it the same way `figma-to-block` reads a Figma node: identify distinct text runs (headings, body copy, button labels) as candidate fields, repeated similar elements (cards, list items) as `repeater` candidates, and images as `image`/`files` fields. Note the actual visible copy from the screenshot — you'll need it in Step 10 if the user wants real content populated, not just the field shape. A screenshot has no exact design tokens (colors/spacing) the way a Figma node's reference code does — approximate visually and say so, rather than presenting a guess as exact.

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

## Step 8 — Write the block's unit test (required, every block, no exceptions)

**Every block created by this skill gets a matching `tests/Unit/Blocks/SectionNameTest.php`, in the same turn, before moving on.** This isn't optional polish — `getData()` is exactly the kind of logic that silently breaks in a future refactor (a renamed field id, a swapped `getMeta()`/`getRepeater()` call, a typo'd array key) without anything catching it until a page renders wrong in production. A fast, WordPress-free unit test catches that class of regression the moment it happens.

**Scope: test only `getData()`'s hydration logic** — does it ask for the right field IDs and assemble them into the right return array — not rendering, not metabox registration, not anything that needs a real WordPress install. Full end-to-end rendering is covered separately by `bin/ci/smoke-test.php`; don't try to duplicate that here.

**The harness already exists** (`phpunit.xml`, `tests/bootstrap.php`, `tests/TestCase.php`, `TAW\Theme\Tests\` PSR-4-mapped to `tests/`) — if this is the first block test in the session and you haven't confirmed it's installed, run `composer run test` once; if it errors with "phpunit not found," run `composer install` first (phpunit/brain-monkey are already in `require-dev`).

**Pattern — copy this shape, adapt the field IDs/mocked methods to the block's actual `getData()`:**

```php
<?php

declare(strict_types=1);

namespace TAW\Theme\Tests\Unit\Blocks;

use TAW\Blocks\SectionName\SectionName;
use TAW\Theme\Tests\TestCase;

final class SectionNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubBlockConstructor();
    }

    public function test_getData_reads_the_expected_field_ids(): void
    {
        $block = $this->getMockBuilder(SectionName::class)
            ->onlyMethods(['getMeta']) // add 'getImageUrl'/'getRepeater' here too if getData() calls them
            ->getMock();

        $block->expects($this->once())
            ->method('getMeta')
            ->with(42, 'section_name_heading') // exact field id from registerMetaboxes()
            ->willReturn('Example Heading');

        $data = $this->callMethod($block, 'getData', 42);

        $this->assertSame('Example Heading', $data['heading']); // exact key getData() returns
    }
}
```

Rules for adapting the pattern:
- **Mock only the methods this specific block's `getData()` actually calls** (`getMeta`, `getImageUrl`, `getRepeater` — whichever subset), via `onlyMethods([...])`. Don't mock methods it doesn't use.
- **Assert the exact field ID strings** passed to each mocked getter (`->with($postId, 'exact_field_id')`), not just `->willReturn(...)` with no argument check — the whole point is catching a mismatch between `registerMetaboxes()`'s field ids and `getData()`'s lookups, and an unchecked `->with()` can't catch that.
- **For a repeater field**, add a second test asserting an empty array passes through untouched (mirrors `tests/Unit/Blocks/FaqTest.php` — read it once as a second worked example alongside the pattern above).
- **If the block has a guard clause** in its template (Step 6's `if (empty($heading)) return;`), you don't need a template-rendering test for that — `index.php` isn't unit-tested by this harness, only `getData()` is.
- Reference example already in the repo: `tests/Unit/Blocks/FaqTest.php`.

Full rationale and the shared `TestCase`/`stubBlockConstructor()` helper: `AGENTS.md` § "Testing Blocks".

## Step 9 — Wire it into a page

If the user also wants this section placed on a page, hand off to the page-assembly workflow: queue it with `BlockRegistry::queue('section_name', ...)` before `get_header()` and render it with `BlockRegistry::render('section_name')` in the right position in the page template. See the `build-page` skill or `AGENTS.md` § "Building a Page — AI Playbook" for full template conventions.

## Step 10 — If sourced from a screenshot, ask about content population

**Skip this step for a plain-text-description request** — it only applies when a screenshot (or other reference image) supplied the field content in Step 3. **If `build-page` invoked this skill and already supplied a population answer for the whole page, use that instead of asking again** — don't re-ask per block when the page-level decision already covers it.

Once the block is wired into a page (Step 9) and a target post exists, ask the user which they want:

1. **Populate real values extracted from the screenshot** — hand off to `populate-content` with the actual copy you read off the image in Step 3. This is a real content write — `populate-content` applies the full confirmation/dry-run safety model from `AGENTS.md` § "Content-writing safety model"; don't skip or shortcut that because the content "obviously" matches the source.
2. **Leave fields empty and rely on template fallbacks** — verify the template actually has sensible fallback rendering for empty fields (Step 6's guard-clause guidance) rather than rendering visibly broken/empty markup.
3. **Fill with Lorem Ipsum placeholder content** — also a real write via `populate-content` (so the admin/preview looks populated for a demo), but using generic placeholder text/rows shaped like the real fields (matching repeater row counts, roughly matching text lengths) rather than the screenshot's actual copy.

If there's no target post yet (the block hasn't been wired into a page, or the page doesn't have a post created), say so and defer this question until one exists — don't populate content against a post that doesn't exist yet.

## Step 11 — Verify

- Confirm the folder name, class name, and `$id` all agree.
- Run `composer dump-autoload` if it wasn't already run by the CLI.
- `php -l` every PHP file you touched or created.
- Run `php bin/ci/check-getdata-signature.php` — same check CI runs, catches the signature bug across every block in one shot, not just the one you just wrote.
- **Run `composer run test`** — confirms the Step 8 test file actually passes, not just that it exists. A test that doesn't run (typo'd namespace, wrong mocked method name) is worse than no test — it looks like coverage but catches nothing.
- **Check the site actually loads** (e.g. `curl -sL -o /dev/null -w '%{http_code}' <site-url>`), not just that this one block's files are syntactically valid. Because every block auto-loads on every request, a signature mismatch (see Step 5.3) or fatal anywhere in `Blocks/` — including in files you didn't touch — takes the whole site down, and that failure mode won't show up from linting your new files alone.
- Optionally, `php bin/taw inspect` reports the live registered blocks/fields — a fast way to confirm the new block is actually discovered and its fields match what you intended, without opening wp-admin.
- If a dev server is running (`npm run dev`), **ask before driving a Playwright browser check** — e.g. "want me to verify the front end render and admin metabox UI in a browser, or are you already looking at it?" On small changes the developer often has the dev site open already and can tell at a glance; only open/drive a browser via Playwright on an explicit yes. This applies to the basic render/admin-UI check here just as much as `visual-check`'s pixel comparison below — neither auto-runs.
- Don't report the task done without at least offering a visual check when a browser is available — see the project's `verify` skill for the general pattern.
- **If this block was sourced from a screenshot**, offer (don't auto-run) the `visual-check` skill once it's wired into a page — it drives a real browser via the Playwright MCP tools to screenshot the rendered section and compares it against the original reference screenshot. Only run it on an explicit yes.

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
- Don't populate real content from a screenshot without asking the three-way question in Step 10 first, and don't skip `populate-content`'s dry-run/confirmation safety model even when the source content "obviously" matches — those gates exist specifically to catch what looks obvious but isn't.
- Don't skip Step 8's unit test, and don't write one that mocks methods the block's `getData()` doesn't actually call, or that asserts return values without checking the field-id arguments passed to the mocked getters — an unchecked test provides false confidence, not coverage.
