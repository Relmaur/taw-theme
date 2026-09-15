---
name: ficha-tecnica
owner: taw
description: >
    Generates a confidencial credentials/access handoff document (WP admin, SSH, FTP, hosting
    panel, registrar, etc.) at reports/confidencial/<slug>-<date>.html, reusing memo-report's
    visual engine in its red "CONFIDENCIAL" variant. Credential values are always prompted for
    interactively, never read from disk; refuses to write unless the target path is verified
    git-ignored first. Triggers on "generate a ficha técnica" / "credentials handoff doc" /
    "ficha-tecnica".
argument-hint: "[optional: client/site name this handoff is for]"
---

## Overview

A client-ownership handoff document — full access credentials (WP admin, SSH, FTP, hosting
panel, domain registrar, whatever applies) in one file, for a client taking full control of their
site. This is the single most sensitive artifact any of these report skills can produce: it is
built entirely around one rule — **the safety gate below runs before a single credential is
asked for, no exceptions, regardless of how the request is phrased.**

Reuses `memo-report`'s shared template and visual engine (read
`.claude/skills/memo-report/assets/report-template.html`'s own header comment for the component
vocabulary) in its inert `variant-confidencial` mode — do not read `memo-report/SKILL.md` for
process steps, though; this skill's own step order below is deliberately different (gate first).

## Step 1 — State what this does, and confirm

Tell the user plainly: this is about to collect live credentials interactively and write them,
in plain text, to an HTML file on disk. Confirm they want to proceed now before continuing.

## Step 2 — Compute the output path

`reports/confidencial/<slug>-<date>.html` — compute this now, before touching anything sensitive.

## Step 3 — Hard gate: verify the path is git-ignored, before any credential prompt

```bash
git check-ignore -v reports/confidencial/<slug>-<date>.html
```

- **Exit 0 (the path is ignored)** — continue to Step 4.
- **Exit 1 (not ignored)** — do **not** collect any credentials yet. Report the problem plainly,
  offer (confirmed — not silent) to append `/reports` to `.gitignore`, then re-run the check. If
  the user declines, stop entirely; do not proceed with credential collection under an unignored
  path under any circumstance.
- **Any other error** (e.g. not a git repository) — stop and report it; don't guess a workaround.

This order is deliberate and must not be reordered: verifying the safety net exists *before*
anything sensitive touches disk, not after.

## Step 4 — Resolve company name and accent color

Same as `memo-report` Step 2 — this is non-sensitive, safe to do once the gate above has passed:

```bash
php bin/taw wp option get _taw_company_name
php bin/taw wp option get _taw_brand_primary_color
```

Missing or empty → ask directly rather than guessing.

## Step 5 — Read the shared template

`.claude/skills/memo-report/assets/report-template.html`. **If it's missing, tell the user to run
the `update-theme` skill first** to pick up current framework skills — do not improvise a
substitute template for a document this sensitive.

## Step 6 — Collect credentials interactively

Use `AskUserQuestion`, grouped by system, **in this fixed order** — the first two groups apply to
every site and are always asked; anything past that is only asked if applicable to this
particular site's setup:

1. **Información General** — site URL, domain name, hosting provider, hosting plan, vencimiento
   (renewal date).
2. **Accesos de Administración (WordPress)** — admin URL, usuario, contraseña.
3. Whatever else applies to this site — SSH / FTP / hosting panel / domain registrar / other.

State up front, explicitly, that values are used only to generate this one file and are never
logged or written anywhere else.

**Never read a value from `wp-config.php`, an environment variable, or any file already on disk —
always ask fresh**, even for a value that's technically already known or discoverable elsewhere
on the system (a hosting invoice, a registrar dashboard, this site's own `wp_options`). This
applies to every field collected here, not just passwords — a domain name or renewal date isn't
secret, but the point of asking is still that the human confirms the exact value going into a
handoff document meant to outlive this session, not that the value is otherwise unknown.

## Step 7 — Compose the document

`{{VARIANT_CLASS}}` = `variant-confidencial` (activates the red accent + the "CONFIDENCIAL — NO
DISTRIBUIR" banner already built into the shared template).

**Fixed opening shape — every ficha técnica opens the same way, regardless of site:**

1. *Información General* — one `.field-table` clause with Step 6 group 1's values.
2. *Accesos de Administración (WordPress)* — one `.field-table` clause with Step 6 group 2's
   values.

Followed by one additional `.field-table` clause section per other credential system collected in
Step 6 (SSH / FTP / hosting panel / registrar / other), numbered 3 onward in whatever order they
were collected — these vary per site, unlike the two fixed leading sections above.

## Step 8 — Write the file — no PDF by default

Write the single HTML file to the path verified in Step 3. **Do not offer a PDF unless asked** —
a PDF would be a second persisted file containing the same secrets, doubling the cleanup surface.
If the user explicitly requests one anyway: warn plainly that this creates a second sensitive
file on disk, get explicit confirmation, then reuse `memo-report`'s Step 6 PDF procedure against
this same HTML file.

## Step 9 — Report and remind

State the output path, confirm (referencing the Step 3 check) that it's git-ignored, and
recommend the developer delete or move the file out of the repo entirely once the handoff is
actually complete. **This skill does not clean up after itself** — it has no way to know when
the handoff is done.

## Don't

- Don't collect a single credential before Step 3's gate has passed with exit 0.
- Don't read any credential value from `wp-config.php`, `.env`, or any other file — always ask.
- Don't offer a PDF by default — this skill's one file is already the maximum footprint by design.
- Don't improvise a different template if the shared one is missing — point at `update-theme`.
- Don't skip confirming the `.gitignore` fix in Step 3 — appending to `.gitignore` is itself a
  change worth showing the user, not a silent side effect of running this skill.
- Don't reorder or drop the two fixed leading sections (*Información General* / *Accesos de
  Administración*) even when a field seems inapplicable to this site — ask the user how to
  handle it (e.g. mark it "N/A") rather than silently omitting the section. The fixed opening
  shape is the point: every ficha técnica this skill produces should read the same way.
