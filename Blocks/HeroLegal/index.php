<?php

/**
 * Hero (Legal) Block Template
 *
 * Implements "I. Hero Section" (node 1:111) from the Figma file
 * "Legal Solutions" — https://www.figma.com/design/odSrhuqv9eMgMZT4y4t52B/Legal-Solutions--1?node-id=1-111
 *
 * Colors and type sizes are one-off brand values from that design (dark
 * navy bg, gold accent, serif display heading) applied as Tailwind arbitrary
 * values rather than global theme tokens — promote to `resources/css/app.css`
 * `@theme` tokens if this becomes the site-wide palette.
 *
 * Font note: the design specifies EB Garamond (heading) and Archivo Narrow
 * (body/labels); neither is self-hosted in this project yet (only Roboto is,
 * per resources/fonts/). Falling back to Tailwind's `font-serif` / `font-sans`
 * stacks below. Add the two webfonts per AGENTS.md's font-loading convention
 * for a pixel-accurate match.
 *
 * Available variables (from HeroLegal::getData):
 *
 * @var string $eyebrow
 * @var array  $heading_lines
 * @var string $subtext
 * @var string $primary_text
 * @var string $primary_url
 * @var string $secondary_text
 * @var string $secondary_url
 */

if (empty($heading_lines) && empty($eyebrow)) {
    return;
}

?>

<section class="hero-legal bg-[#12212b] px-20 py-25">
    <div class="hero-legal__container section-container mx-auto max-w-360 w-[90%]">
        <div class="hero-legal__content flex flex-col items-start max-w-3xl">
            <?php if ($eyebrow) : ?>
                <div class="hero-legal__eyebrow border-l-2 border-[#b08d3e] pl-[18px] mb-4">
                    <p class="font-serif text-[12px] tracking-[1.2px] uppercase text-[#b08d3e]"><?php echo esc_html($eyebrow); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($heading_lines)) : ?>
                <h1 class="hero-legal__heading font-serif font-semibold text-[#f7f5f0] text-[64px] leading-[1.1] tracking-[-1.28px] mb-6">
                    <?php foreach ($heading_lines as $line) : ?>
                        <?php echo esc_html($line); ?><br>
                    <?php endforeach; ?>
                </h1>
            <?php endif; ?>

            <?php if ($subtext) : ?>
                <p class="hero-legal__subtext font-sans text-[18px] leading-[1.65] text-[#9fb0ba] mb-8 max-w-md"><?php echo esc_html($subtext); ?></p>
            <?php endif; ?>

            <?php if ($primary_text || $secondary_text) : ?>
                <div class="hero-legal__ctas flex items-start gap-4">
                    <?php if ($primary_text) : ?>
                        <a href="<?php echo esc_url($primary_url ?: '#'); ?>"
                           class="hero-legal__cta hero-legal__cta--primary bg-[#b08d3e] text-[#12212b] font-serif text-[12px] tracking-[1.2px] uppercase text-center px-8 py-[17px]">
                            <?php echo esc_html($primary_text); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($secondary_text) : ?>
                        <a href="<?php echo esc_url($secondary_url ?: '#'); ?>"
                           class="hero-legal__cta hero-legal__cta--secondary border border-[#d8d3c8] text-[#f7f5f0] font-serif text-[12px] tracking-[1.2px] uppercase text-center px-8 py-[17px]">
                            <?php echo esc_html($secondary_text); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
