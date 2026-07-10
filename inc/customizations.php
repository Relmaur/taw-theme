<?php

/**
 * TAW Theme — Developer Customisations
 *
 * This is your file. It is never touched by `update-theme` — add whatever
 * site-specific hooks belong here. Loaded automatically by
 * TAW\Core\Theme\Theme::bootstrapFullSite() if it exists; safe to delete
 * if this site needs none of the defaults below.
 */

add_action('admin_init', function () {
    remove_post_type_support('page', 'editor');
});

add_action('after_setup_theme', function () {
    // Textdomain loading is handled by Theme::bootstrapFullSite() itself,
    // on an earlier after_setup_theme priority than this callback — don't
    // add load_theme_textdomain() here, it would just double-load. See
    // Theme.php's bootstrapFullSite() docblock.

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary Menu', 'taw-theme'),
        'footer'  => __('Footer Menu', 'taw-theme'),
    ]);
});
