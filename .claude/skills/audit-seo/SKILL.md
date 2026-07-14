---
name: audit-seo
description: >
    Audits a page's (or the whole site's) copy AND SEO meta — title tag, meta description,
    social/OG image — for SEO and conversion quality, using `php bin/taw seo:extract`/
    `seo:inject`. Asks whether to apply approved rewrites directly to the database or produce a
    polished, shareable client-approval report (Artifact) instead. Triggers on "audit SEO for
    page [ID]" / "review copy on post [ID]" / "audit SEO for the whole site" / "audit-seo".
argument-hint: "<post_id> | --all"
---

## Overview

Two CLI primitives do the mechanical work, shipped in `taw/core`:

- **`php bin/taw seo:extract <post_id>`** (or `--all` for every published page/post in one run)
  — walks every registered Metabox field for the post, keeps only non-empty `text`/`textarea`/
  `wysiwyg` content (including inside repeater rows, recursively), plus SEO meta (title,
  description, social image — see below), and writes a hierarchical JSON dump. Deliberately
  excludes image/URL/ID/layout fields from the copy itself — this is a copy audit, not a
  general field dump.
- **`php bin/taw seo:inject <post_id>`** (or `--all` for a site-wide dump) — writes an edited
  copy of that same JSON shape back. Validates every field against the live registry *before*
  writing anything for a given post (all-or-nothing per post — a half-applied rewrite is worse
  than a rejected one; with `--all`, one post failing validation doesn't block the others). For
  repeater rows, merges only the touched text sub-fields into the *current* live row rather than
  replacing the whole row, so an image/URL sub-field sitting alongside the copy is never lost.

**SEO meta is real, not just page copy.** Every dump includes a `seo_meta` object: the page's
`<title>` tag content, `<meta name="description">`, and social share (Open Graph/Twitter card)
image — the actual fields search engines and link previews read, effectively a from-scratch
Yoast-style pass since most TAW sites don't have an SEO plugin installed. `seo_meta.source` tells
you where these live right now: `"taw_native"` (TAW's own fields — the common case), `"yoast"`
(a Yoast install is active; TAW writes to Yoast's own fields so its UI stays in sync), or
`"unsupported"` (a different SEO plugin, e.g. RankMath, is active — TAW can't write its meta yet;
say so plainly and skip the meta portion of the audit for that site, don't attempt a workaround).

The analysis itself (keyword presence, copywriting impact, readability) is judgment this skill
performs directly — the CLI only ever does mechanical extraction and validated writing, never
scoring or rewriting on its own.

## Action 1 — Extract

Single post:
```bash
php bin/taw seo:extract <post_id> --output=.taw/seo-dump.json
```

Whole site ("audit SEO for the whole site" / "site-wide audit"):
```bash
php bin/taw seo:extract --all --output=.taw/seo-site-dump.json
```

If a single-post extract reports 0 fields across 0 blocks (and empty `seo_meta`), say so plainly
and stop — either the post has no TAW block content, or every field on it is genuinely empty.
Don't fabricate an analysis of content that isn't there. For `--all`, posts with nothing to
report just contribute nothing to the audit — that's expected, not an error.

## Action 2 — Read the dump

Single-post shape: `post_id`/`post_title`/`post_type`/`post_status`, `seo_meta` (see below), and
`blocks[]` — each block has an id, a metabox title, and a flat list of fields (`field_id`,
`label`, `type`, `value`) or, for a repeater, `rows` (each row a flat object of its text
sub-fields only). `--all`'s shape is `{"posts": [<single-post-shape>, ...]}`.

**`seo_meta` shape:**
```json
{
  "source": "taw_native",
  "meta_title": "",
  "meta_description": "",
  "og_image_id": 0,
  "og_image_url": "",
  "featured_image_id": 0,
  "candidate_images": [{"field_id": "hero_image", "label": "Image", "attachment_id": 12, "url": "..."}]
}
```
`og_image_id: 0` means no social image is currently set — never treat this as an image to "set
to 0," it round-trips unchanged unless you deliberately pick a real attachment ID.
`candidate_images` lists this post's own already-uploaded images (its featured image, plus any
image-type Metabox field with a value) — the source pool for Action 3's image recommendation.

**Field-role heuristic** (the dump doesn't label this explicitly — infer it):
- A field whose `field_id`/`label` contains "heading"/"title" is a heading-equivalent — the
  first one on the page (usually the hero block) is the effective H1, later ones are H2/H3-tier.
- A field whose `field_id`/`label` contains "cta"/"button" is a call-to-action label, not body
  copy — judge it on persuasiveness and specificity, not paragraph structure.
- Everything else (`textarea`, `wysiwyg`, plain `text` fields not matching the above) is body
  copy.

## Action 3 — Analyze

For each post, assess both the page copy and the SEO meta — they're evaluated differently:

**Page copy**, per block:
1. **Keyword presence (H1/H2)** — does the inferred heading tier carry the page's actual target
   topic, or is it generic ("Welcome", "Lorem Ipsum," a placeholder never replaced)? Note
   keyword absence as a Red Flag, not a Polish Opportunity — a missing primary keyword in the
   H1 is a real SEO gap, not a nice-to-have.
2. **Copywriting impact** — active vs. passive voice, CTA specificity ("Learn More" vs. "See
   Pricing for Your Team Size"), and whether body copy leads with a benefit or a feature.
3. **Readability** — paragraph/sentence density in `textarea`/`wysiwyg` fields (a wall of text
   with no natural break is a Red Flag), and whether repeater rows (FAQ items, testimonials,
   etc.) are consistent in length and tone with each other.

**SEO meta** (skip this section entirely if `seo_meta.source` is `"unsupported"` — say so, don't
propose meta rewrites TAW can't currently write):
1. **Meta title** — empty is a Red Flag (falls back to the raw post title, which is rarely
   optimized copy). Present but generic/duplicated across pages is a Polish Opportunity.
2. **Meta description** — empty is a Red Flag (search engines auto-generate one from page
   content, unpredictably). Present but not compelling (no reason to click, no call to action)
   is a Polish Opportunity. Flag anything over ~155 characters — it truncates in search results.
3. **Social image** — if `og_image_id` is 0, decide what to propose:
   - `candidate_images` non-empty → recommend the best one (prefer the featured image, else the
     most prominent/first image field) as a Polish Opportunity, naming which image and why.
   - `candidate_images` empty (nothing usable already on the post) → ask the user, via
     AskUserQuestion: **"Point me to an image"** (they give an attachment ID, URL, or
     description; resolve it and include it in the proposed rewrites) or **"Skip for now"**
     (note in the report that no social image is set and none was available to recommend; don't
     block the rest of the audit on this).

For `--all`, aggregate rather than repeating the same structure per page in the conversation —
Action 4's report is where the per-page detail belongs.

## Action 4 — Report

Present a crisp markdown report, not a wall of prose. Single post:

```markdown
## SEO & Copy Audit — Post <id> ("<title>")

### 🚩 Red Flags
- **hero.hero_heading** — "Lorem Ipsum" is placeholder text, never replaced. No keyword, no message.
- **SEO meta** — no meta description set; search engines will auto-generate one.

### ✨ Polish Opportunities
- **hero.hero_cta_text** — "Learn More" is generic. Consider "See Pricing for Your Team" (specific, benefit-led).
- **Social image** — none set. Recommend the Hero section's image (already uploaded, on-brand).

### Proposed rewrites
| Field | Current | Proposed |
|---|---|---|
| hero_heading | Lorem Ipsum | Get In Touch With Our Team |
| hero_cta_text | Learn More | See Pricing for Your Team |
| *SEO meta title* | *(empty)* | Contact Our Team \| Acme Co. |
| *SEO meta description* | *(empty)* | Get in touch for pricing, support, or partnerships — we respond within one business day. |
```

For `--all`, structure the report per-post (one `##` section per page that had findings), plus a
short site-wide summary at the top (how many pages audited, how many had Red Flags, common
patterns worth calling out — e.g. "6 of 8 pages have no meta description set").

Every proposed rewrite must map to a real `field_id` from the dump, or to `seo_meta`'s
`meta_title`/`meta_description`/`og_image_id` — never propose a rewrite for anything else (other
image/URL/layout fields are out of scope here — that's `fields:set`'s job, not this skill's).

## Action 5 — Choose how this gets approved

Never assume which mode fits — the same audit serves two genuinely different situations (a
developer reviewing their own work vs. a client who needs to sign off on copy changes before
they go live), and picking wrong either slows down routine internal work or writes unapproved
copy to a client's live site. Ask, via AskUserQuestion, right after presenting the Action 4
report:

- **"Apply directly to the database"** — for internal/developer review: go straight into the
  dry-run → confirm → write flow below (Action 6a). Appropriate when whoever is running this
  skill is themselves authorized to approve the copy.
- **"Generate a client-approval report"** — for anything that needs sign-off from someone who
  isn't in this conversation: produces a polished, shareable deliverable (Action 6b) and writes
  nothing to the database this run.
- **"Both — generate the report now, and apply after you confirm"** — for a paper trail even on
  internal-only work, or when the approval is expected imminently in the same session: produces
  the report, then still runs the full Action 6a approval flow afterward.

## Action 6a — Apply directly (only after explicit approval)

This follows the same mandatory content-writing safety model every field-writing skill in this
project follows (`populate-content`, `fields:set`) — not a lighter version of it:

1. **Show the full rewrite plan before touching anything** — the table from Action 4 already
   is that plan (page copy and SEO meta together). Get explicit approval for the whole batch,
   not per-field.
2. **Confirm before overwriting non-empty content** — page-copy fields are non-empty by
   construction (extraction drops empty fields); an SEO meta field going from empty to populated
   is new content, not an overwrite, but still part of the same batch approval.
3. **Confirm before writing to a published post** — check `post_status` in the dump; a live
   page is inherently higher-stakes than a draft, flag it explicitly in the approval ask. For
   `--all`, summarize how many of the affected posts are published vs. draft.
4. Only after approval: apply the approved rewrites into a copy of the dump JSON (page-copy
   fields under `blocks`, meta rewrites under `seo_meta`), save as `.taw/seo-optimized.json`
   (or `.taw/seo-site-optimized.json` for `--all`), and run:
   ```bash
   php bin/taw seo:inject <post_id> --input=.taw/seo-optimized.json --dry-run
   # or: php bin/taw seo:inject --all --input=.taw/seo-site-optimized.json --dry-run
   ```
   Show the dry-run output — it's the actual sanitized values that would be written, a final
   concrete check before the real write.
5. Once the dry-run looks right, run without `--dry-run`.
6. **If `seo:inject` refuses** a field (unknown field, wrong field type, a repeater row-count
   mismatch, or an SEO plugin gone `"unsupported"` since Action 1), don't retry with a
   workaround — re-run `seo:extract` to re-sync with current data, re-apply the approved
   rewrites to the fresh dump, and re-attempt. For `--all`, a single post being rejected doesn't
   block the others (already reported per-post) — just re-sync and retry that one post.

## Action 6b — Generate a client-approval report

Load the `artifact-design` skill first, then publish an HTML Artifact — not a markdown dump —
since this deliverable's whole purpose is to be shared with someone non-technical who needs to
approve copy and meta changes, not read a diff. Build it from the Action 3 analysis, but written
for that audience:

- Lead with a short plain-language summary (what was reviewed — one page or the whole site, how
  many issues found, at a glance) before any detail — a client opens this wanting the headline,
  not the methodology.
- Present Red Flags and Polish Opportunities as visually distinct sections (color/iconography,
  per the `artifact-design` skill's guidance) — never raw JSON, `field_id`s, or block names in
  the client-facing copy; use the field's plain-language `label` and a description of where it
  appears on the page instead ("the main heading at the top of the page", not `hero_heading`;
  "the search-result preview text", not `seo_meta.meta_description`).
- Show every proposed rewrite (page copy and SEO meta alike) as a clear before/after comparison
  (not a diff syntax) with a one-line rationale for each — a client approves *because* they
  understand *why*, not just *what* changed.
- For a site-wide audit, organize by page with a clear per-page heading, plus the same top-level
  summary described in Action 4.
- End with an explicit call to action telling the client what approving means ("once approved,
  these changes will be published to the live site") and how to give that approval back to
  whoever commissioned the audit.

This publishes as a private Artifact by default — tell the user the link, and that it's theirs
to share with the client when ready; don't imply it's already been sent anywhere. **Nothing is
written to the database in this action** — that only ever happens via Action 6a, whether that's
this same session (the "Both" choice) or a later one once approval comes back.

## Don't

- Don't skip Action 1 and hand-write a JSON payload from memory — always extract fresh, so the
  dump reflects the post's (or site's) actual current state, not a stale assumption.
- Don't propose or apply a rewrite for a field `seo:extract` didn't return — image, URL, and
  layout fields (other than the social image, via `seo_meta`) are out of scope for this skill by
  design (token efficiency, and because `seo:inject` will refuse them anyway).
- Don't propose SEO meta rewrites when `seo_meta.source` is `"unsupported"` — say plainly that a
  different SEO plugin is active and this skill can't write its meta yet, don't attempt a
  workaround or write to TAW's own (unused, unrendered) fields instead.
- Don't treat `og_image_id: 0` in a dump as an instruction to set the image to ID 0 — it means
  "no image currently set." Only a real, deliberately-chosen attachment ID counts as a change.
- Don't guess a social image when `candidate_images` is empty — ask via AskUserQuestion
  ("point me to an image" vs. "skip for now"), per Action 3.
- Don't apply any rewrite without the Action 6a approval step, even for a single field, even for
  an obviously-bad placeholder like "Lorem Ipsum" — approval is required regardless of how
  confident the proposed rewrite is.
- Don't skip Action 5's AskUserQuestion and assume which mode fits — a solo developer's routine
  cleanup and a client's live-site copy are not the same risk profile, and guessing wrong either
  slows down harmless work or writes unapproved copy to a live site.
- Don't put raw `field_id`s, block names, or JSON into the Action 6b client deliverable — it's
  for a non-technical audience approving copy, not reviewing a diff.
- Don't touch `post_title`/`post_content`/`post_status` — `seo:inject` only ever writes Metabox
  fields and SEO meta (same hard boundary `fields:set` documents for core post data).
- Don't retry `seo:inject` with `--force` or by hand-editing around a validation failure — every
  rejection (unknown field, wrong type, row-count mismatch, unsupported SEO plugin) is signaling
  something real changed or was assumed incorrectly; re-extract and re-plan instead of pushing
  through it.
