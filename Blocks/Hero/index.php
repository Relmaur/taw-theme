<?php

/**
 * @var string     $heading
 * @var string     $tagline
 * @var int|string $image_id
 * @var string     $cta_text
 * @var string     $cta_url
 */

if (empty($heading) && empty($tagline)) {
    return;
}

use TAW\Blocks\Button\Button;
use TAW\Helpers\Image;
?>

<section class="hero" <?php echo taw_editor_section('hero'); ?>>
    <div class="hero__container flex flex-col md:flex-row items-center gap-10 mx-auto max-w-360 w-[90%] py-16">
        <div class="hero__content flex-1 flex flex-col justify-center">
            <?php if ($tagline): ?>
                <p class="hero__tagline text-sm uppercase tracking-wide text-gray-500"><?php echo taw_editable($tagline, 'hero', 'hero_tagline'); ?></p>
            <?php endif; ?>
            <?php if ($heading): ?>
                <h1 class="hero__heading text-4xl md:text-5xl font-bold mt-2"><?php echo taw_editable($heading, 'hero', 'hero_heading'); ?></h1>
            <?php endif; ?>
            <?php if ($cta_text): ?>
                <div class="hero__cta mt-6">
                    <?php (new Button())->render(['text' => $cta_text, 'url' => $cta_url ?: '#']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($image_id): ?>
            <div class="hero__image w-full flex-1">
                <?php echo Image::render((int) $image_id, 'full', esc_attr($heading), [
                    'above_fold' => true,
                    'sizes'      => '100vw',
                    'class'      => 'hero-image w-full rounded-lg',
                    'attr'       => taw_editor_attrs_array('hero', 'hero_image'),
                ]); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
