<?php

/**
 * @var string $heading
 * @var array  $items
 */

if (empty($items)) {
    return;
}

use TAW\Core\Seo\Schema;

// Same $items the accordion below renders from — the FAQPage node and the
// visible markup can never drift out of sync with each other.
Schema::push(Schema::faqPage($items));
?>

<section class="faq" <?php echo taw_editor_section('faq'); ?>>
    <div class="faq__container mx-auto max-w-360 w-[90%] py-16">
        <?php if ($heading): ?>
            <h2 class="faq__heading text-3xl font-bold mb-8"><?php echo taw_editable($heading, 'faq', 'faq_heading'); ?></h2>
        <?php endif; ?>

        <div class="faq__list flex flex-col gap-4" x-data="{ open: null }">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $question = $item['question'] ?? '';
                $answer = $item['answer'] ?? '';
                if ($question === '') {
                    continue;
                }
                ?>
                <div class="faq__item border-b border-gray-200 pb-4">
                    <button
                        type="button"
                        class="faq__question flex w-full items-center justify-between gap-4 text-left font-semibold"
                        @click="open = open === <?php echo (int) $index; ?> ? null : <?php echo (int) $index; ?>"
                        :aria-expanded="open === <?php echo (int) $index; ?>"
                    >
                        <span><?php echo esc_html($question); ?></span>
                        <span class="faq__icon transition-transform" :class="{ 'rotate-180': open === <?php echo (int) $index; ?> }" aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="faq__answer mt-2 text-gray-600" x-show="open === <?php echo (int) $index; ?>" x-cloak>
                        <?php echo wp_kses_post(wpautop($answer)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
