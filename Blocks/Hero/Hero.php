<?php

declare(strict_types=1);

namespace TAW\Blocks\Hero;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

/**
 * Minimal MetaBlock example shipped with a fresh TAW install. Field shape
 * matches AGENTS.md § "Common Section Catalog" → Hero verbatim — read that
 * catalog before adding more sections; this block is the reference example
 * it's built to match, not a one-off.
 */
class Hero extends MetaBlock
{
    protected string $id = 'hero';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'      => 'taw_hero',
            'title'   => __('Hero Section', 'taw-theme'),
            'screens' => ['page'],
            'fields'  => [
                ['id' => 'hero_heading',  'label' => __('Heading', 'taw-theme'),  'type' => 'text',  'required' => true, 'width' => '50', 'placeholder' => __('Lorem ipsum dolor sit amet', 'taw-theme')],
                ['id' => 'hero_tagline',  'label' => __('Tagline', 'taw-theme'),  'type' => 'text',  'width' => '50', 'placeholder' => __('Consectetur adipiscing elit', 'taw-theme')],
                ['id' => 'hero_image',    'label' => __('Image', 'taw-theme'),    'type' => 'image', 'width' => '50'],
                ['id' => 'hero_cta_text', 'label' => __('CTA Text', 'taw-theme'), 'type' => 'text',  'width' => '25', 'placeholder' => __('Get Started', 'taw-theme')],
                ['id' => 'hero_cta_url',  'label' => __('CTA URL', 'taw-theme'),  'type' => 'url',   'width' => '25'],
            ],
        ]);
    }

    protected function getData(int|false $postId): array
    {
        return [
            'heading'  => $this->getMeta($postId, 'hero_heading'),
            'tagline'  => $this->getMeta($postId, 'hero_tagline'),
            'image_id' => $this->getMeta($postId, 'hero_image'),
            'cta_text' => $this->getMeta($postId, 'hero_cta_text'),
            'cta_url'  => $this->getMeta($postId, 'hero_cta_url'),
        ];
    }
}
