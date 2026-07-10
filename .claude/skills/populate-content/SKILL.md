---
name: populate-content
description: >
    Fills in a block's metabox fields (or an OptionsPage field) on a real post with content from
    a document, list, or plain-language description the user provides — e.g. "fill in the
    team_members repeater on the About page with this list" or "populate the FAQ block on post 12
    from this doc". Writes via `php bin/taw fields:set`, with mandatory dry-run previews and
    confirmation before any risky write. Not for scaffolding new blocks — see `make-metablock`.
argument-hint: "<target post/page> <field or block> <source content — pasted text, a file, or a description>"
---

## Overview

This skill turns a source document (pasted text, an attached file, a list, or a plain description) into real field values on a real post, using `php bin/taw fields:get`/`fields:set` — the same primitive the Visual Editor's REST endpoint uses, so writes are sanitized exactly like a real admin form save. It does **not** create blocks or fields — if the target field/block doesn't exist yet, hand off to `make-metablock` first.

**Source of truth:** `AGENTS.md` § "`fields:get` / `fields:set`" and § "Content-writing safety model" — read the safety model before writing anything. This skill is the procedural wrapper around those rules, not a replacement for them.

**This skill is security-sensitive by design.** It writes directly to a live WordPress database. Treat every step in "Safety gates" below as mandatory, not optional — skipping the dry-run or the confirmation gate because a change "looks small" is exactly the failure mode this skill exists to prevent.

## Step 1 — Identify the target post and field(s)

Resolve unambiguously before doing anything else:

- **Post:** if the user names a page/post by title or slug, confirm the ID with `wp post list --s="<title>" --fields=ID,post_title,post_status` (see AGENTS.md's WP-CLI section for the Local by Flywheel socket quirk if this fails with a DB connection error). If more than one result plausibly matches, list them and ask which one — never guess.
- **Field(s):** run `php bin/taw inspect --json` and match the user's description (field id, label, or block name) against the registered field list. If the description is ambiguous (e.g. two blocks on the same post both have a `heading` field), ask which one. If the field genuinely doesn't exist yet, stop and say so — offer to hand off to `make-metablock` rather than guessing at a field id that isn't registered.

For a group sub-field, use the compound id exactly as `inspect` reports it (e.g. `hero_cta_text`), same as `fields:set` itself expects.

## Step 2 — Map the source content to the field's shape

- **Scalar fields** (`text`, `textarea`, `wysiwyg`, `url`, `number`, `color`, `datepicker`) — the source content usually maps directly; extract the relevant excerpt if the source document covers more than this one field.
- **Repeaters** — the source is usually a list (bullet points, a table, numbered items). Map each source item to a row, and each row's sub-fields to the repeater's registered sub-field ids (from `inspect --json`'s field config). **If the source document's structure doesn't obviously correspond to the sub-field ids** (e.g. the doc has "Title" but the field is `role`), show your proposed mapping and confirm before proceeding — don't silently guess a mapping that might misfile content into the wrong sub-field.
- **`image`/`files` fields** — `fields:set` cannot upload media; it only accepts existing attachment IDs. If the source references images:
  - Try to find a matching existing attachment by filename/alt text (`wp eval` querying `wp_posts` where `post_type='attachment'`, or the REST search endpoint).
  - If found, confirm the match with the user before using it — a filename/alt-text match is a guess, not a certainty.
  - If no match exists, skip the image field, note it in your final report, and tell the user it needs a manual upload (or ask if they want to provide an attachment ID directly).
- **`post_select`** — resolve referenced posts to IDs the same way as the target post in Step 1 (search by title, confirm if ambiguous), never guess an ID.

Build the full JSON payload for each field before moving to Step 3 — don't interleave mapping and writing.

## Step 3 — Dry run

For every field, run:

```bash
php bin/taw fields:get <post_id> <field_id> --json      # current value, if any
php bin/taw fields:set <post_id> <field_id> --file=/tmp/<field>.json --dry-run --json   # or inline value for scalars
```

Use `--file` for anything repeater/array-shaped — never fight shell quoting on a multi-row JSON payload. Compare the dry-run's sanitized output against what you intended to write; if the sanitizer stripped or changed something unexpected (e.g. a tag you expected to survive didn't), investigate before proceeding rather than writing it anyway.

## Step 4 — Safety gates (mandatory)

Apply every rule in `AGENTS.md` § "Content-writing safety model". In short, before any real (non-dry-run) write:

1. If the current value (from Step 3's `fields:get`) is non-empty, show old vs. new and get explicit confirmation.
2. If the post's status is `publish`, confirm regardless of whether the field is currently empty.
3. If this touches multiple fields and/or multiple posts, show the **entire plan** — every post, field, and value — in one summary and get one confirmation for the whole batch before writing any of it.
4. If any target field is `wysiwyg` or has `'sanitize' => 'code'` and the source content came from outside this conversation (a pasted document, a fetched file), flag that explicitly and confirm the content is trusted, even if otherwise low-risk.
5. If the request implies changing `post_title`/`post_content`/`post_status` too, say so explicitly and treat it as separate from this skill's scope — don't fold a core-post-data change into a `fields:set` plan.

A brand-new empty field on a draft post, populated with content the user already supplied and approved in this same request, still gets a quick confirmation showing what will be written — just without the heavier "old vs new" comparison, since there's no old value to compare against.

## Step 5 — Write and verify

After confirmation, write each field for real (drop `--dry-run`), then `fields:get` every field you just wrote to confirm what actually persisted — report the final stored value, not just "done", since sanitization can legitimately change the input (stripped tags, coerced IDs, re-encoded JSON).

## Step 6 — Report

Summarize: what was written (post, field, before → after value), what was skipped (e.g. unmatched images) and why, and anything the user needs to do manually (upload an image, create a missing `post_select` target). Don't report success on fields you skipped.

## Don't

- Don't call `fields:set` for real without a preceding `--dry-run` on the exact same payload.
- Don't overwrite a non-empty field without showing old vs. new and getting confirmation first.
- Don't write to a `publish`-status post without confirmation, even for an empty field.
- Don't guess an attachment ID or a `post_select` target ID from a filename/title match without confirming — resolve or skip, never guess silently.
- Don't batch-confirm by asking once and then also silently expanding scope mid-batch (e.g. discovering more matching posts partway through and writing to them without re-confirming).
- Don't touch `post_title`, `post_content`, `post_status`, or any other core post field — this skill is Metabox/OptionsPage-only, via `fields:set`.
- Don't create new blocks or fields to make content "fit" — that's `make-metablock`'s job; hand off instead of improvising a field that isn't registered.
