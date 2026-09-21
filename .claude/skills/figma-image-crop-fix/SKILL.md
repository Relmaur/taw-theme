---
name: figma-image-crop-fix
owner: taw
description: >
    Fixes images that don't match their Figma composition — wrong zoom, wrong crop anchor, a
    photo Figma stretches instead of crops, a graphic that should float over a background panel
    instead of filling it edge-to-edge. Pulls the exact crop/position transform from Figma's own
    generated CSS (get_design_context) and applies it as inline positioning on the live `<img>`
    (or `object-fit`/`object-position` where the container has no fixed aspect ratio), instead of
    guessing or re-exporting a flattened raster asset. Triggers on "this image doesn't look like
    Figma" / "the crop is off" / "make the images match the design exactly" /
    "figma-image-crop-fix". Part of the `figma-fidelity` pass.
argument-hint: "<block id(s) or 'all images' to audit against Figma>"
---

## Overview

A `taw/core` `image` field stores a WordPress attachment ID and (almost always) renders through
`Image::render(..., ['class' => '... object-cover'])`. That's correct for a plain full-bleed
photo. It silently breaks whenever the Figma design applies its own scale/position transform to
an image fill — which Figma does constantly: a tight face-crop on an avatar, an icon-style
illustration deliberately kept small and off-center over a tinted panel, a photo crop anchored
away from dead-center. `object-fit:cover object-position:center` cannot reproduce any of that; it
just computes its own center-anchored cover crop, which frequently looks "zoomed wrong" or
"positioned wrong" next to the Figma reference. And sometimes Figma isn't cropping at all — it is
*stretching* the image into a box of a different ratio (see Step 2).

**The fix is almost never re-exporting the image.** The asset uploaded to WordPress is the same
file Figma uses (same pixel dimensions), so a mismatch is a crop/fit problem, not a wrong asset.
Figma's own `get_design_context` output already hands you the exact reproduction, as a CSS pattern:
an `overflow-hidden` clip container with an absolutely-positioned `<img>` sized/offset in
percentages relative to that container (`h-[139.95%] w-[129.71%] left-[-40.53%] top-[-19.95%]`).
Copy those numbers into an inline `style` on the live `<img>` — in a container of the right
shape — and the render matches Figma responsively, at full source resolution, with zero
re-encoding.

A "recompose and re-export a flattened asset in an image editor" approach works, but is strictly
worse: it bakes one fixed composition (looks wrong at any other container aspect ratio), costs a
lossy re-encode generation, and needs an editor round-trip per image. Only reach for actual image
editing when the **content** of the asset is wrong (wrong photo, needs retouching, needs a
genuinely new crop nobody has designed yet) — not for a positioning mismatch Figma has already
solved.

## Step 1 — Get the node ID(s)

Resolve the Figma `fileKey`/`nodeId` for each image in scope, same as `figma-to-block` Step 1. If
auditing "all images," walk every block's registered `image`-type fields (`php bin/taw inspect
--json` lists them) and match each to its Figma node via the block's own design reference.

## Step 2 — Pull the exact transform (desktop AND mobile)

Call `get_design_context` on the specific image node (not its parent card/frame — the innermost
node actually named "Image" or holding the raster fill), **for the desktop frame and again for the
mobile sibling** (see the mobile section in Step 4). Read the returned JSX's `<img>` class list:

- **`object-cover` with no offset classes** → already correct. No action needed; this is a plain
  full-bleed crop and the current `Image::render(..., ['class' => '... object-cover'])` call
  already reproduces it.
- **`absolute h-[X%] w-[Y%] left-[-A%] top-[-B%]`** (inside a `relative overflow-hidden`
  container) → a pan/zoom transform; needs the fix in Step 3. These percentages are relative to
  the *container*, not the image's own size — don't reinterpret them.
- **`size-full` (often `object-bottom`/`object-center`) with NO `object-cover` and no percentage
  offsets** → Figma renders the asset with `object-fit: fill`: it is **stretched** to the box, not
  cropped. (Example: a 1024×576 photo squashed into a 496×250 frame.) `object-cover` then crops
  content Figma shows in full — e.g. cuts off the top of a building. Confirm by pixel-diffing
  Figma's screenshot region against a stretch render and against cover renders at each vertical
  offset (recipe in `figma-fidelity` § "Images": stretch scored 2.4, every cover offset ≥ 25).
  Reproduce with `object-fill` inside an **`aspect-[W/H]`-locked box** so the squash is identical
  at every width; a box that can change ratio would change the distortion too.

Do this for every image in scope before changing any code — a quick per-image triage table (node
id → "fine" / "pan-zoom" / "stretch" → the numbers) keeps the actual edits mechanical.

## Step 3 — Apply the transform

For a single (non-repeater) image field, replace the `object-cover` render call:

```php
<?php echo Image::render((int) $image_id, 'large', esc_attr($alt), [
    'class' => 'absolute max-w-none pointer-events-none',
    'attr'  => ['style' => 'height:139.95%;width:129.71%;left:-40.53%;top:-19.95%;'],
]); ?>
```

inside a container that's already `relative overflow-hidden` (every card/banner pattern in this
theme already is, since that's needed for the rounded-corner clip). Keep any `-z-10`/stacking
classes the original `object-cover` version had.

For a **repeater** field, each row's image typically has its *own* transform (Figma composed each
photo/illustration individually) — there's no formula linking them. Build a small index-keyed
lookup array at the top of the template and pick from it inside the loop, with a plain
`object-cover` fallback for any row beyond the ones Figma actually specified (so a client adding a
4th card later degrades gracefully instead of erroring):

```php
$image_transforms = [
    ['height' => '139.95%', 'width' => '129.71%', 'left' => '-40.53%', 'top' => '-19.95%'], // row 0
    ['height' => '156.42%', 'width' => '151.06%', 'left' => '-37.8%',  'top' => '-28.1%'],  // row 1
];
// ...
foreach ($items as $i => $item) {
    $t = $image_transforms[$i] ?? null;
    // $t ? positioned <img> : plain object-cover fallback
}
```

Comment each array with which card/row it belongs to, and note in the code comment that these
numbers are tied to *that specific uploaded image* — if it's ever swapped for a differently
composed replacement, re-derive from that node's `get_design_context` output, don't reuse the old
numbers. A block reused across posts with unrelated photos can key the lookup by attachment ID
instead of row index.

## Step 4 — Gotchas

- **WP's `'thumbnail'` size is hard-cropped to a square before your transform ever sees it.** If
  the transform assumes the source's original (non-square) aspect ratio — which any real zoom/pan
  transform does — rendering through `'thumbnail'` will apply the percentages to an
  already-wrong-shaped image and produce nonsense. Use `'medium'` or `'large'` (WP's proportional,
  uncropped sizes) for any image getting a custom transform.
- **A container without its own opaque background** will show whatever's behind it through any part
  of the transform that doesn't fully cover. That is usually correct — check whether the Figma
  reference shows a background panel colour peeking around the image (a graphic that deliberately
  doesn't reach the card's edge). Don't add a background-colour fix; it's almost always already
  provided by a parent and the gap is intentional.
- **The percentages are relative to the container's actual rendered box — if that box is a
  different shape than the one Figma computed them against, the crop looks visibly wrong (typically
  "too wide"/zoomed oddly) even though every number was copied correctly.** A shared fluid
  section class (`.section-shell` = `padding-inline: clamp(1rem, 5.5vw, 160px)` or similar) does
  **not** reproduce every Figma section's actual margins — Figma varies margins per section (e.g.
  160px "content" sections vs 32px "wide" card grids), while a `clamp()` formula yields one fluid
  value that lands on neither. **Before trusting a transform, measure the container's rendered
  width/aspect ratio (`getBoundingClientRect()`) against the Figma node's own width at the
  reference frame size.** If it differs, give that specific section's wrapper an explicit width
  matching Figma (e.g. `mx-auto w-full max-w-[1440px] px-4 md:px-8`) rather than touching the
  shared class, which would shift every other section. See `figma-fidelity` § "Section spacing".
- **A container whose height is content-driven (no fixed aspect ratio at all) can never safely
  take the percentage transform.** A full-bleed hero background behind a copy column is the
  canonical case: its height is however tall the heading/body/CTA render, which varies with
  content length and viewport width — there is no single ratio Figma's percentages could match.
  Applying the transform there doesn't just mis-crop, it visibly **stretches** the image (explicit
  percent width+height override the image's intrinsic ratio with no `object-fit` to preserve it).
  Use `object-fit: cover` + a zoom + a derived `object-position` instead. For a top-left-anchored
  zoom (Figma `left ≈ 0`):
  - `zoom = Figma image width% ÷ 100`
  - `object-position-y = (|top%| × frame height ÷ zoom) ÷ (cover height − frame height)`, where
    *cover height* = frame width × image height ÷ image width
  - apply as `transform: scale(zoom); transform-origin: 0 0; object-position: center Y%`.

  Worked example: Figma places a 2752×1536 photo at width 103.89% / left 0 / top −29.87% of a
  1440×442 frame — 1496×835, shifted up 132px. Cover at that frame is 1440×804 (slack 362px), so
  `zoom = 1.0389` and `object-position-y = 132.03 ÷ 1.0389 ÷ 361.75 = 35.13%`. Unlike the
  percentage transform this stays covering (never stretches, never leaves gaps) at any width or
  content height. A horizontal Figma shift on top of the zoom can't be expressed this way — use an
  aspect-locked box instead.

  **The zoom overflows its box by `(zoom − 1) × width`.** If that image sits in a width-capped,
  centred wrapper with edge fades at the cap (e.g. `max-w-[1920px]` with gradient fades blending
  into the section background), the overflow escapes past the fade on ultra-wide viewports and
  shows as an unfaded bright band. Put `overflow-hidden` on the capped wrapper. Check this at
  ~2400px, not just at the Figma frame width.

  Conversely, when the container **is** pinned to Figma's own frame ratio in CSS
  (`aspect-ratio: 375/320` on a mobile-only image slot that mirrors a dedicated Mobile frame), the
  verbatim percentage transform is exactly right and safe at any width — `aspect-ratio` keeps the
  box's proportions locked, which is what makes the percentages hold. A block can legitimately use
  both: `object-position` for the fluid desktop background, the verbatim transform for a
  fixed-ratio mobile slot.

- **A desktop crop transform must never be reused on mobile — Figma's Mobile frame has its own crop
  *and* its own box shape for every image.** Desktop percentages assume the desktop card's
  proportions (say 325×520); the mobile card is a different shape (343×384…), so the same
  percentages stretch the image (ratios of 0.18×, 1.43×, 0.85× have all been measured on one page).
  For every image, pull the *Mobile* sibling node's own `get_design_context` too, and:
  - carry both crops on one `<img>` as CSS custom properties
    (`style="--m-h:..;--m-w:..;--m-l:..;--m-t:..;--d-h:..;--d-w:..;--d-l:..;--d-t:.."`) with classes
    `h-(--m-h) w-(--m-w) left-(--m-l) top-(--m-t) md:h-(--d-h) md:w-(--d-w) md:left-(--d-l) md:top-(--d-t)`;
  - pin the mobile container to Figma's mobile ratio with `aspect-[W/H] md:aspect-auto md:h-[…]`,
    **not** a fixed pixel height, or the percentages drift between 375px and 767px;
  - if the mobile card is content-height, don't use a percentage box at all: `object-cover` +
    `[object-position:var(--m-pos)]`, where the position = Figma's mobile
    `top ÷ (image height − card height)`;
  - always keep `object-cover` on the `<img>` as a safety net (except for the deliberate
    `object-fill` stretch case) so a container that ever drifts crops instead of stretching;
  - when Figma's mobile layout is *structurally* different (image block stacked above the panel vs
    behind it on desktop), restructure the markup — the same wrapper can be an in-flow block on
    mobile and `md:absolute md:inset-0` on desktop.

## Step 5 — Verify

Check by measuring, not by eye: for every `<img>`, `(box w/h) ÷ (natural w/h)` should be ≈ 1.00
(or the image must have `object-fit: cover`, or be a deliberate `object-fill`), at desktop width
and again at 375px via device emulation — a narrow window can't go below ~500px. Then compare a
viewport screenshot of the rendered section against a fresh `get_screenshot` of the same Figma
node, pixel-diffing the photo region when a crop is in question (`figma-fidelity` § "Images").

**Ask before driving a browser** (`CLAUDE.md` § "Don't", `visual-check`). `composer run phpstan`
and `composer run test` should stay clean — these are template-only changes, no PHP signature or
data model touched.

## Don't

- Don't reach for an image editor as the first move — derive and apply the CSS first; it covers the
  overwhelming majority of "doesn't match Figma" cases for already-correct source assets.
- Don't guess `object-position` values by eye against a screenshot. Figma's `get_design_context`
  gives exact numbers; use them (or derive them with the formula above).
- Don't apply a repeater row's transform to a different row's image, and don't assume rows share
  one formula — each is an individual designer choice.
- Don't apply a percentage transform to a content-height container, or a desktop transform to
  mobile.
- Don't assume Figma is cropping — check for the stretch (`object-fit: fill`) case first when a
  photo looks "cut off".
- Don't forget the `'thumbnail'`-size trap above; it's the single most likely way this fix silently
  fails to reproduce Figma even though the numbers are correct.
