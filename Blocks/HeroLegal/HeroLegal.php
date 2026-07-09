<?php

declare(strict_types=1);

/**
 * Implements "I. Hero Section" (node 1:111) from the Figma file
 * "Legal Solutions" — https://www.figma.com/design/odSrhuqv9eMgMZT4y4t52B/Legal-Solutions--1?node-id=1-111
 *
 * Named HeroLegal (not Hero) because the existing Hero block is a
 * different visual pattern (light theme, image-right layout) and
 * carries unrelated demo fields — see AGENTS.md / make-metablock skill
 * notes on Hero being a kitchen-sink block.
 */

namespace TAW\Blocks\HeroLegal;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

class HeroLegal extends MetaBlock
{
    protected string $id = 'hero_legal';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'      => 'taw_hero_legal',
            'title'   => __('Hero (Legal) Section', 'taw-theme'),
            'screens' => ['page'],
            'fields'  => [
                ['id' => 'hero_legal_eyebrow', 'label' => __('Eyebrow', 'taw-theme'), 'type' => 'text', 'width' => '50', 'placeholder' => __('CLÁUSULA I', 'taw-theme')],
                [
                    'id'          => 'hero_legal_heading',
                    'label'       => __('Heading', 'taw-theme'),
                    'type'        => 'textarea',
                    'rows'        => 3,
                    'required'    => true,
                    'description' => __('One line per row to control the manual line breaks shown in the design.', 'taw-theme'),
                ],
                [
                    'id'    => 'hero_legal_subtext',
                    'label' => __('Supporting text', 'taw-theme'),
                    'type'  => 'textarea',
                    'rows'  => 3,
                ],
                ['id' => 'hero_legal_primary_cta_text', 'label' => __('Primary CTA text', 'taw-theme'), 'type' => 'text', 'width' => '25', 'placeholder' => __('Agenda una consulta', 'taw-theme')],
                ['id' => 'hero_legal_primary_cta_url',  'label' => __('Primary CTA URL', 'taw-theme'),  'type' => 'url',  'width' => '25'],
                ['id' => 'hero_legal_secondary_cta_text', 'label' => __('Secondary CTA text', 'taw-theme'), 'type' => 'text', 'width' => '25', 'placeholder' => __('Conocer los servicios', 'taw-theme')],
                ['id' => 'hero_legal_secondary_cta_url',  'label' => __('Secondary CTA URL', 'taw-theme'),  'type' => 'url',  'width' => '25'],
            ],
        ]);
    }

    protected function getData(int|false $postId): array
    {
        return [
            'eyebrow'        => $this->getMeta($postId, 'hero_legal_eyebrow'),
            'heading_lines'  => array_filter(explode("\n", (string) $this->getMeta($postId, 'hero_legal_heading'))),
            'subtext'        => $this->getMeta($postId, 'hero_legal_subtext'),
            'primary_text'   => $this->getMeta($postId, 'hero_legal_primary_cta_text'),
            'primary_url'    => $this->getMeta($postId, 'hero_legal_primary_cta_url'),
            'secondary_text' => $this->getMeta($postId, 'hero_legal_secondary_cta_text'),
            'secondary_url'  => $this->getMeta($postId, 'hero_legal_secondary_cta_url'),
        ];
    }
}
