<?php

declare(strict_types=1);

namespace TAW\Blocks\Hero;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

class Hero extends MetaBlock
{
    protected string $id = 'hero';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'     => 'taw_hero',
            'title'  => __('Hero Section', 'taw-theme'),
            'screen' => 'page',
            'fields' => [
                [
                    'id'       => 'hero_heading',
                    'label'    => __('Heading', 'taw-theme'),
                    'type'     => 'text',
                    'width'    => '33.33',
                    'required' => true,
                    'editor' => true
                ],
                [
                    'id'       => 'hero_tagline',
                    'label'    => __('Tagline', 'taw-theme'),
                    'type'     => 'text',
                    'width'    => '33.33',
                    'required' => true,
                    'editor'   => [      // ← Editable with settings
                        'max_length' => 200,
                    ],
                ],
                [
                    'id' => 'hero_content',
                    'label' => __('Content', 'taw-theme'),
                    'type' => 'wysiwyg',
                    'editor' => [      // ← Editable with settings
                        'media_buttons' => false,
                        'textarea_rows' => 5,
                    ],
                ],
                [
                    'id'    => 'hero_image_url',
                    'label' => __('Hero Image', 'taw-theme'),
                    'type'  => 'image',
                    'width' => '33.33',
                    'editor' => [      // ← Editable with settings
                        'preview_size' => 'medium',
                    ],
                ],
                [
                    'id'          => 'hero_show_tagline',
                    'label'       => __('Show Tagline', 'taw-theme'),
                    'type'        => 'checkbox',
                    'description' => __('Enable or disable the tagline above the heading.', 'taw-theme'),
                    'width'       => '33.33'
                ],
                [
                    'id'          => 'hero_padding',
                    'label'       => __('Hero Padding', 'taw-theme'),
                    'type'        => 'range',
                    'min'         => 20,
                    'max'         => 200,
                    'step'        => 10,
                    'unit'        => 'px',
                    'default'     => 80,
                    'description' => __('Vertical padding for the hero section.', 'taw-theme'),
                    'width'       => '33.33',
                ],
                [
                    'id'          => 'hero_bg_color',
                    'label'       => __('Background Color', 'taw-theme'),
                    'type'        => 'color',
                    // 'default'     => '#0f172a',
                    'description' => __('Background color for the hero section.', 'taw-theme'),
                    'width'       => '33.33',
                ],
                [
                    'id'          => 'featured_post',
                    'label'       => __('Featured Post', 'taw-theme'),
                    'type'        => 'post_select',
                    'post_type'   => 'post,page',
                    'description' => __('Select a single post to feature.', 'taw-theme'),
                    'width'       => '50'
                ],
                [
                    'id'          => 'related_posts',
                    'label'       => __('Related Posts', 'taw-theme'),
                    'type'        => 'post_select',
                    'post_type'   => 'post',
                    'multiple'    => true,
                    'max'         => 5,
                    'description' => __('Select up to 5 related posts.', 'taw-theme'),
                    'width'       => '50'
                ],
                [
                    'id'           => 'team_members',
                    'label'        => __('Team Members', 'taw-theme'),
                    'type'         => 'repeater',
                    'button_label' => __('Add Team Member', 'taw-theme'),
                    'max'          => 8,
                    'fields'       => [
                        [
                            'id'          => 'name',
                            'label'       => __('Name', 'taw-theme'),
                            'type'        => 'text',
                            'placeholder' => __('Full name', 'taw-theme'),
                            'width'       => '50',
                        ],
                        [
                            'id'          => 'role',
                            'label'       => __('Role', 'taw-theme'),
                            'type'        => 'text',
                            'placeholder' => __('e.g. Designer, Developer', 'taw-theme'),
                            'width'       => '50',
                        ],
                        [
                            'id'    => 'bio',
                            'label' => __('Bio', 'taw-theme'),
                            'type'  => 'textarea',
                            'rows'  => 3,
                        ],
                        [
                            'id'    => 'avatar',
                            'label' => __('Photo', 'taw-theme'),
                            'type'  => 'image',
                            'width' => '50',
                        ],
                        [
                            'id'     => 'group',
                            'label'  => __('Group', 'taw-theme'),
                            'type'   => 'group',
                            'fields' => [
                                [
                                    'id'          => 'linkedin',
                                    'label'       => __('LinkedIn URL', 'taw-theme'),
                                    'type'        => 'text',
                                    'placeholder' => 'https://linkedin.com/in/username',
                                    'width'       => '50',
                                ],
                                [
                                    'id'          => 'twitter',
                                    'label'       => __('Twitter URL', 'taw-theme'),
                                    'type'        => 'text',
                                    'placeholder' => 'https://twitter.com/username',
                                    'width'       => '50',
                                ],
                            ],
                            'width' => '50'
                        ],
                    ],
                    'description' => __('Add your team members. Drag to reorder.', 'taw-theme'),
                ],
                [
                    'id' => 'nested_repeater',
                    'label' => __('Nested Repeater', 'taw-theme'),
                    'type' => 'repeater',
                    'button_label' => __('Add Item', 'taw-theme'),
                    'fields' => [
                        [
                            'id' => 'nested_text',
                            'label' => __('Nested Text', 'taw-theme'),
                            'type' => 'text',
                        ],
                        [
                            'id' => 'nested_repeater_repeater',
                            'label' => __('Nested Repeater', 'taw-theme'),
                            'type' => 'repeater',
                            'button_label' => __('Add Sub Item', 'taw-theme'),
                            'fields' => [
                                [
                                    'id' => 'sub_nested_text',
                                    'label' => __('Sub Nested Text', 'taw-theme'),
                                    'type' => 'text',
                                ],
                                [
                                    'id' => 'sub_sub_nested_repeater',
                                    'label' => __('Sub Sub Nested Repeater', 'taw-theme'),
                                    'type' => 'repeater',
                                    'button_label' => __('Add Sub Sub Item', 'taw-theme'),
                                    'fields' => [
                                        [
                                            'id' => 'sub_sub_nested_text',
                                            'label' => __('Sub Sub Nested Text', 'taw-theme'),
                                            'type' => 'text',
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]
            ],
        ]);
    }

    protected function getData(int $postId): array
    {
        $image_url = $this->getMeta($postId, 'hero_image_url') ? $this->getMeta($postId, 'hero_image_url') : 'https://placehold.co/600x400';

        return [
            'heading' => $this->getMeta($postId, 'hero_heading'),
            'tagline' => $this->getMeta($postId, 'hero_tagline'),
            'content' => $this->getMeta($postId, 'hero_content'),
            'nested' => $this->getMeta($postId, 'nested_repeater'),
            'image_url' => $image_url
        ];
    }
}
