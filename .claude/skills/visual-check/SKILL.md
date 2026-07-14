---
name: visual-check
description: >
    Opt-in pixel-accuracy check that screenshots a section or full page as actually rendered
    in the local dev site (via the Playwright MCP browser) and compares it against the design
    reference (a Figma node screenshot or the screenshot the user originally supplied), reporting
    concrete visual deviations. Only ever offered — never run automatically — after
    `make-metablock`/`figma-to-block` finishes a section or `build-page` finishes a full page.
    Requires the developer to explicitly say yes; deciding whether a page is "pixel-perfect" and
    ready for production is a human call, so this never runs unattended and is never wired into
    CI, `composer run phpstan`, or any other automated check.
argument-hint: "<block id or page URL to check> [+ figma.com URL or reference screenshot, if not already in context]"
---

## Overview

This skill is the visual "does the rendered page actually match the design" gate. It exists because everything else in `make-metablock` / `figma-to-block` / `build-page`'s own Verify steps checks that code runs (site loads, `php -l`, signature checks) — none of it looks at the page. This skill closes that gap by using a real browser (Playwright MCP) to capture what's actually on screen, then relies on Claude's own vision to compare it against the design reference image side by side in the same turn.

**This is a judgment tool, not a pass/fail gate.** It surfaces concrete deviations for a developer to weigh — it does not itself decide whether the page is production-ready.

## When to offer this (never run it unprompted)

Offer this once, as a plain question, right after:
- `figma-to-block` or `make-metablock` finishes a section built from a Figma node or screenshot, at its Verify step
- `build-page` finishes wiring a full page together, at its Step 7

Only offer it when there's actually a design reference to compare against (a Figma source or an originally-supplied screenshot) **and** a dev server is reachable. If either is missing, say so instead of offering ("no dev server running, so I can't do a live visual check") rather than silently skipping the offer.

Phrase it as a real question the developer can decline, e.g.: *"Want me to do a pixel-accuracy check against the design now?"* Proceed only on an explicit yes. Do not fold this into the standard Verify checklist as if it always runs — it is additional and optional every time.

## Step 1 — Confirm prerequisites

- A dev server must be serving current assets: check `ViteLoader::isDevServerRunning()` is true (or that `npm run dev` is running) — a visual check against unbuilt/unstyled markup is worse than useless, it actively misleads.
- The Playwright MCP tools must be available (added via this project's `.mcp.json`). If they're not connected yet, tell the user to run `claude mcp` / restart the session to pick up the `playwright` server, rather than falling back to a guess.
- Resolve the actual local URL for the page/section under test (site base URL + slug from the page template, or the block's containing page if checking a single section).

## Step 2 — Capture the rendered section or page

Using the Playwright MCP browser tools:
1. Navigate to the resolved URL and wait for the page to be idle (network + any obvious animation/transition settle).
2. Set the viewport to match the design reference's frame width (desktop-first unless the request specifies a breakpoint) before capturing — a viewport mismatch invalidates the comparison before it starts.
3. **Single section:** scroll the section into view and screenshot just that element (target it by a stable selector — an `id`/`data-*` attribute on the block's wrapper if the template has one, otherwise the full viewport framed on that section is an acceptable fallback, but say which you used).
4. **Full page:** take a full-page screenshot (not just the viewport) so every section is captured top to bottom.
5. If the design has meaningfully different breakpoints worth checking (e.g. the Figma file has separate mobile/desktop frames), ask whether to check more than one viewport rather than assuming — don't multiply screenshots the user didn't ask for.

## Step 3 — Get the reference image

- **Figma-sourced section/page:** call `get_screenshot` on the same `fileKey`/`nodeId` used when the block was built.
- **Screenshot-sourced section/page:** reuse the image the user originally pasted/attached — don't ask them to re-supply it if it's still in context.
- If neither is available, ask the user to attach one now; don't proceed on a guess of what the design looked like.

## Step 4 — Compare visually

Present the rendered screenshot and the reference image in the same response so they're both directly visible, then compare them yourself (Claude's own vision) rather than asserting a match. Call out concrete, specific deviations — spacing/padding, alignment, color values, font/weight, image cropping, missing or extra elements, text overflow/wrapping — not a vague "looks close." Structure findings as a short list, one line per deviation, each naming the element and what's off. If nothing meaningful differs, say so plainly rather than padding the list.

If the user wants an objective diff score or a highlighted-diff image on top of the visual read, offer to write a one-off `pixelmatch`/`odiff` comparison script into the scratchpad directory rather than adding a persistent devDependency to the project — only do this if asked, it's not part of the default flow.

## Step 5 — Report and hand the decision back

Report the deviations found (or confirm none were found) and stop there. This skill's job ends at "here's what's actually different" — it does not fix anything automatically and does not declare the page done. If the developer wants fixes applied, treat that as a new, separate request and confirm the specific changes before editing any block/template code.

## Don't

- Don't run this automatically at the end of `make-metablock`, `figma-to-block`, or `build-page` — always ask first, and proceed only on an explicit yes.
- Don't wire this into CI, `composer run phpstan`, `bin/ci/*`, or any GitHub Actions workflow. Judging pixel fidelity and production-readiness is a decision for the developer sitting at the keyboard, not an automated gate — keep it entirely out of anything that runs unattended.
- Don't capture a screenshot with the dev server not running or serving stale/unbuilt assets — you'd be comparing the design against an unstyled page and reporting false deviations.
- Don't silently fix any deviation you find as part of this skill's own flow — report first, let the developer decide what's worth changing.
- Don't leave a stray browser session/tab open across unrelated checks — close it when done so it doesn't linger between unrelated tasks.
