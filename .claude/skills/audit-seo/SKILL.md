---
name: audit-seo
description: >
    Audits a page's copy for SEO and conversion quality using `php bin/taw seo:extract`/
    `seo:inject`, then proposes and (with approval) applies rewrites. Triggers on "audit SEO
    for page [ID]" / "review copy on post [ID]" / "audit-seo".
argument-hint: "<post_id>"
---

## Overview

Two CLI primitives do the mechanical work, shipped in `taw/core`:

- **`php bin/taw seo:extract <post_id>`** — walks every registered Metabox field for the post,
  keeps only non-empty `text`/`textarea`/`wysiwyg` content (including inside repeater rows,
  recursively), and writes a hierarchical JSON dump grouped by block. Deliberately excludes
  image/URL/ID/layout fields — this is a copy audit, not a general field dump.
- **`php bin/taw seo:inject <post_id>`** — writes an edited copy of that same JSON shape back.
  Validates every field against the live registry *before* writing anything (all-or-nothing —
  a half-applied rewrite is worse than a rejected one), and for repeater rows, merges only the
  touched text sub-fields into the *current* live row rather than replacing the whole row, so
  an image/URL sub-field sitting alongside the copy in that row is never touched or lost.

The analysis itself (keyword presence, copywriting impact, readability) is judgment this skill
performs directly — the CLI only ever does mechanical extraction and validated writing, never
scoring or rewriting on its own.

## Action 1 — Extract

```bash
php bin/taw seo:extract <post_id> --output=.taw/seo-dump.json
```

If it reports 0 fields across 0 blocks, say so plainly and stop — either the post has no TAW
block content, or every text field on it is genuinely empty. Don't fabricate an analysis of
content that isn't there.

## Action 2 — Read the dump

Read `.taw/seo-dump.json`. Each block has an id, a metabox title, and a flat list of fields
(`field_id`, `label`, `type`, `value`) or, for a repeater, `rows` (each row a flat object of
its text sub-fields only).

**Field-role heuristic** (the dump doesn't label this explicitly — infer it):
- A field whose `field_id`/`label` contains "heading"/"title" is a heading-equivalent — the
  first one on the page (usually the hero block) is the effective H1, later ones are H2/H3-tier.
- A field whose `field_id`/`label` contains "cta"/"button" is a call-to-action label, not body
  copy — judge it on persuasiveness and specificity, not paragraph structure.
- Everything else (`textarea`, `wysiwyg`, plain `text` fields not matching the above) is body
  copy.

## Action 3 — Analyze

For each block, assess:

1. **Keyword presence (H1/H2)** — does the inferred heading tier carry the page's actual target
   topic, or is it generic ("Welcome", "Lorem Ipsum," a placeholder never replaced)? Note
   keyword absence as a Red Flag, not a Polish Opportunity — a missing primary keyword in the
   H1 is a real SEO gap, not a nice-to-have.
2. **Copywriting impact** — active vs. passive voice, CTA specificity ("Learn More" vs. "See
   Pricing for Your Team Size"), and whether body copy leads with a benefit or a feature.
3. **Readability** — paragraph/sentence density in `textarea`/`wysiwyg` fields (a wall of text
   with no natural break is a Red Flag), and whether repeater rows (FAQ items, testimonials,
   etc.) are consistent in length and tone with each other.

## Action 4 — Report

Present a crisp markdown report, not a wall of prose:

```markdown
## SEO & Copy Audit — Post <id> ("<title>")

### 🚩 Red Flags
- **hero.hero_heading** — "Lorem Ipsum" is placeholder text, never replaced. No keyword, no message.

### ✨ Polish Opportunities
- **hero.hero_cta_text** — "Learn More" is generic. Consider "See Pricing for Your Team" (specific, benefit-led).

### Proposed rewrites
| Field | Current | Proposed |
|---|---|---|
| hero_heading | Lorem Ipsum | Get In Touch With Our Team |
| hero_cta_text | Learn More | See Pricing for Your Team |
```

Every proposed rewrite must map to a real `field_id` from the dump — never propose a rewrite
for a field that doesn't exist, and never propose rewriting a field this skill didn't extract
(image/URL/layout fields are out of scope here — that's `fields:set`'s job, not this skill's).

## Action 5 — Apply (only after explicit approval)

This follows the same mandatory content-writing safety model every field-writing skill in this
project follows (`populate-content`, `fields:set`) — not a lighter version of it:

1. **Show the full rewrite plan before touching anything** — the table from Action 4 already
   is that plan. Get explicit approval for the whole batch, not per-field.
2. **Confirm before overwriting non-empty content** — everything this skill touches was
   non-empty by construction (extraction drops empty fields), so this is never skippable here.
3. **Confirm before writing to a published post** — check `post_status` in the dump; a live
   page is inherently higher-stakes than a draft, flag it explicitly in the approval ask.
4. Only after approval: apply the approved rewrites into a copy of the dump JSON, save as
   `.taw/seo-optimized.json`, and run:
   ```bash
   php bin/taw seo:inject <post_id> --input=.taw/seo-optimized.json --dry-run
   ```
   Show the dry-run output — it's the actual sanitized values that would be written, a final
   concrete check before the real write.
5. Once the dry-run looks right, run without `--dry-run`:
   ```bash
   php bin/taw seo:inject <post_id> --input=.taw/seo-optimized.json
   ```
6. **If `seo:inject` refuses** (unknown field, wrong field type, or a repeater row-count
   mismatch), don't retry with a workaround — a row-count mismatch specifically means the live
   data changed since Action 1's extract (someone edited the post in the admin meanwhile).
   Re-run `seo:extract`, re-apply the approved rewrites to the fresh dump, and re-attempt.

## Don't

- Don't skip Action 1 and hand-write a JSON payload from memory — always extract fresh, so the
  dump reflects the post's actual current state, not a stale assumption.
- Don't propose or apply a rewrite for a field `seo:extract` didn't return — image, URL, and
  layout fields are out of scope for this skill by design (token efficiency, and because
  `seo:inject` will refuse them anyway).
- Don't apply any rewrite without the Action 5 approval step, even for a single field, even for
  an obviously-bad placeholder like "Lorem Ipsum" — approval is required regardless of how
  confident the proposed rewrite is.
- Don't touch `post_title`/`post_content`/`post_status` — `seo:inject` only ever writes Metabox
  fields (same hard boundary as `fields:set`), and this skill doesn't work around that.
- Don't retry `seo:inject` with `--force` or by hand-editing around a validation failure — every
  rejection (unknown field, wrong type, row-count mismatch) is signaling something real changed
  or was assumed incorrectly; re-extract and re-plan instead of pushing through it.
