<?php

declare(strict_types=1);

namespace TAW\Blocks\FAQ;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

/**
 * Accordion-style FAQ section — field shape matches AGENTS.md § "Common
 * Section Catalog" → FAQ verbatim. Also the reference example for pairing
 * a block's own markup with schema.org structured data: index.php pushes
 * a FAQPage JSON-LD node (via TAW\Core\Seo\Schema::faqPage()) onto the
 * page's graph alongside the accordion HTML — the two are built from the
 * same $items data, never duplicated by hand.
 */
class FAQ extends MetaBlock
{
    protected string $id = 'faq';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id' => 'taw_faq',
            'title' => __('FAQ Section', 'taw-theme'),
            'screens' => ['page'],
            'fields' => [
                ['id' => 'faq_heading', 'label' => __('Heading', 'taw-theme'), 'type' => 'text', 'width' => '50'],
                [
                    'id' => 'faq_items',
                    'label' => __('Questions', 'taw-theme'),
                    'type' => 'repeater',
                    'button_label' => __('Add Question', 'taw-theme'),
                    'fields' => [
                        ['id' => 'question', 'label' => __('Question', 'taw-theme'), 'type' => 'text', 'required' => true],
                        ['id' => 'answer', 'label' => __('Answer', 'taw-theme'), 'type' => 'textarea', 'rows' => 4],
                    ],
                ],
            ],
        ]);
    }

    protected function getData(int|false $postId): array
    {
        return [
            'heading' => $this->getMeta($postId, 'faq_heading'),
            'items' => $this->getRepeater($postId, 'faq_items'),
        ];
    }
}
