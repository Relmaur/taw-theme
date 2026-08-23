<?php

/**
 * Client post-submission portal — form registration.
 *
 * Paired with page-client-submit.php (Template Name: "Client Post
 * Submission") and PagePassword-gated there. Registered here, not in the
 * template, per taw-core's CLAUDE.md: admin-ajax.php never runs theme
 * templates, so a form registered inside one wouldn't exist by the time a
 * submission comes in.
 *
 * Included from inc/customizations.php — commented out by default there,
 * since this is a per-client feature, not something every taw-theme site
 * should get automatically.
 */

use TAW\Core\Form\Form;

add_action('init', function () {
    $categories = get_categories(['hide_empty' => false]);
    $categoryOptions = [];
    foreach ($categories as $category) {
        $categoryOptions[$category->term_id] = $category->name;
    }

    Form::register([
        'id'           => 'client_post_submission',
        'submit_label' => __('Submit for Review', 'taw-theme'),
        'messages'     => [
            'success' => __('Thanks! Your post has been submitted for review.', 'taw-theme'),
        ],
        'fields' => [
            [
                'id'       => 'post_title',
                'label'    => __('Title', 'taw-theme'),
                'type'     => 'text',
                'required' => true,
            ],
            [
                'id'       => 'post_body',
                'label'    => __('Body', 'taw-theme'),
                'type'     => 'wysiwyg',
                'required' => true,
            ],
            [
                'id'      => 'post_category',
                'label'   => __('Category', 'taw-theme'),
                'type'    => 'select',
                'options' => $categoryOptions,
                // Optional — an unset category lets wp_insert_post() fall
                // through to WordPress's own default-category behavior
                // rather than forcing the client to pick one.
            ],
            [
                'id'    => 'featured_image',
                'label' => __('Featured Image', 'taw-theme'),
                'type'  => 'image',
                // Optional — the reviewer can add one later if omitted.
            ],
        ],
        // No 'email' config — Form's own default (no config at all) already
        // emails admin_email on every submission, which doubles as a "go
        // review this new draft" notification for free.
        'on_submit' => function (array $data) {
            $postId = wp_insert_post([
                'post_title'   => $data['post_title'],
                'post_content' => $data['post_body'],
                'post_status'  => 'draft',
                'post_type'    => 'post',
                'post_category' => !empty($data['post_category']) ? [(int) $data['post_category']] : [],
            ], true);

            if (is_wp_error($postId)) {
                throw new \RuntimeException($postId->get_error_message());
            }

            if (!empty($data['featured_image'])) {
                set_post_thumbnail($postId, (int) $data['featured_image']);
            }
        },
    ]);
});
