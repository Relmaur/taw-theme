<?php

/**
 * Hero Component Template
 * 
 * Available variables (from Hero::getData):
 * 
 * @var string $tagline
 * @var string $heading
 * @var string $image_url
 * @var string $content
 */

// Guard: don't render if there's no content
if (empty($heading) && empty($tagline)) {
    return;
}

use TAW\Blocks\Button\Button;
use TAW\Helpers\Image;

$button = new Button();

// dump($nested);

?>

<section class="hero" <?php echo taw_editor_section('hero'); ?>>
    <div class="section-container flex justify-center items-stretch gap-10 mx-auto max-w-360 w-[90%]">
        <div class=" hero__content flex-1 flex flex-col justify-center">
            <?php if ($tagline): ?>
                <p class="hero__tagline"><?php echo taw_editable($tagline, 'hero', 'hero_tagline'); ?></p>
            <?php endif; ?>
            <?php if ($heading): ?>
                <!-- <h1 class="hero__heading text-5xl"><?php echo esc_html($heading); ?></h1> -->
                <h1 class="hero__heading text-5xl"><?php echo taw_editable($heading, 'hero', 'hero_heading'); ?></h1>
            <?php endif; ?>
            <?php if ($content): ?>
                <div class="hero__text mt-4 text-lg text-gray-700"><?php echo taw_editable($content, 'hero', 'hero_content'); ?></div>
            <?php endif; ?>
            <div class="flex items-center justify-start mt-2 gap-2">
                <?php $button->render(['text' => __('Get Started', 'taw-theme'), 'url' => '/contact']); ?>
                <?php $button->render(['text' => __('Learn More', 'taw-theme'), 'url' => '/about', 'variant' => 'secondary']); ?>
            </div>
        </div>
        <?php if ($image_url): ?>
            <div class="image w-full max-w-200">
                <?php echo Image::render((int) $image_url, 'full', 'Hero banner', [
                    'above_fold' => true,
                    'sizes'      => '100vw',
                    'class'      => 'hero-image w-full',
                    'attr'       => array_merge(
                        ['style' => 'width: 100%'],
                        taw_editor_attrs_array('hero', 'hero_image_url')
                    ),
                ]); ?>
            </div>
        <?php endif; ?>
    </div>
</section>