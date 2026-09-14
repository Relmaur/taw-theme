---
name: memo-report
owner: taw
description: >
    Produces a status memo (feature shipped, current state, roadmap) as reports/<slug>-<date>.html
    using TAW's fixed visual report system (letterhead, memo header, status board, numbered clause
    sections, sign-off), optionally converted to PDF via local headless Chrome. Company name and
    accent color come from this site's OptionsPage (_taw_company_name / _taw_brand_primary_color)
    if set, otherwise asked directly. Triggers on "write a memo about..." / "generate a status
    report" / "memo-report".
argument-hint: "[optional: subject/topic of the memo]"
---

## Overview

A fixed, on-brand visual system for status memos — the same letterhead/memo-block/status-board/
clause structure every time, so output quality doesn't depend on how carefully a given session
hand-authors HTML. This skill fills in content; it does not redesign the template per request.

**Source of truth for the visual system:** `.claude/skills/memo-report/assets/report-template.html`
— its own top-of-file comment block documents every structural component. Read that comment block
before filling the template; don't guess at class names.

## Step 1 — Gather the memo content

- If the request is "write a memo about what we just built" (or similarly scoped to recent
  conversation), draft the content from context: subject, the Para/De/Fecha/Asunto header fields,
  and the body sections (status board rows if there's a natural list of discrete items with
  differing states, clause sections for anything narrative).
- Otherwise ask directly for the subject and who it's to/from.
- **Confirm the drafted section outline with the user before writing the file** — a short list of
  proposed clause titles/board rows is enough; don't silently commit to a structure they haven't
  seen.

## Step 2 — Resolve company name and accent color

```bash
php bin/taw wp option get _taw_company_name
php bin/taw wp option get _taw_brand_primary_color
```

Either command failing (non-zero exit — option not set) or returning empty means that value isn't
configured on this site. **Never hard-require either field** — most sites won't have
`brand_primary_color` set yet, and some may not have `company_name` either. When missing, ask the
user directly (`AskUserQuestion`) for a company name and a hex accent color rather than guessing
or defaulting to an arbitrary brand color.

## Step 3 — Read the shared template

`.claude/skills/memo-report/assets/report-template.html`, relative to the repo root — same
repo-root-relative convention this codebase already uses for `bin/taw`, `wp-config.php`, etc.

## Step 4 — Compute the output path

`reports/<slug>-<date>.html`, where `<slug>` is a short kebab-case slug of the subject and
`<date>` is `YYYY-MM-DD`. `mkdir -p reports` if it doesn't exist yet.

**Check `.gitignore` for a `/reports` line before writing anything.** The canonical scaffold
already ships one — if it's missing (a site's `.gitignore` predates this, or was hand-edited),
ask (confirmed, not silent) before appending it. Generated reports routinely contain
client-specific business content that has no reason to be committed.

## Step 5 — Fill the template and write the file

Replace every `{{TOKEN}}` in the template with real content — see the template's own header
comment for the full token list and component vocabulary. Leave `{{VARIANT_CLASS}}` empty (this
skill only ever produces the plain variant; the confidencial red variant is `ficha-tecnica`'s).

## Step 6 — Offer a PDF

Default to yes if Chrome is found at `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`
(macOS-only path — if this site's session is on a different OS, ask instead of assuming a path).

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --disable-gpu --virtual-time-budget=5000 \
  --print-to-pdf="<abs>/reports/<slug>-<date>.pdf" \
  "file://<abs>/reports/<slug>-<date>.html"
```

`--virtual-time-budget=5000` matters — it gives the Google Fonts `<link>` time to load before the
page is rasterized; without it the PDF can silently fall back to system fonts with no error.

Verify success two ways: `file <path>` reports "PDF document", and the file's size is greater
than 0. On either failure, or if Chrome isn't found at that path, **report the failure plainly and
tell the user to open the HTML file and use Print → Save as PDF instead** — never claim a PDF was
produced without having verified it.

## Step 7 — Report

State the exact output path(s) created (HTML, and PDF if produced).

## Don't

- Don't skip Step 1's confirmation and write straight from an assumed outline.
- Don't hardcode a company name or accent color when the OptionsPage fields are empty — ask.
- Don't write into `reports/` before confirming it's gitignored.
- Don't claim a PDF was created without verifying it via `file` + a non-zero size check.
- Don't modify `report-template.html`'s structure to fit one memo's content — if the fixed
  component set (board/clause/field-table/callout/blockquote) genuinely can't express something,
  say so and ask the user how to proceed, rather than quietly extending the shared template for a
  one-off need (that's how the "same output quality every time" guarantee erodes).
