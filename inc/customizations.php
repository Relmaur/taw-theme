<?php

/**
 * TAW Theme — Developer Customisations
 *
 * This is your file. It is never touched by `update-theme` — add whatever
 * site-specific hooks belong here. Loaded automatically by
 * TAW\Core\Theme\Theme::bootstrapFullSite() if it exists; safe to delete
 * if this site needs none of the defaults below.
 */

// TAW Media (nestable Media Library folders) is opt-in at the taw/core
// level, but ships active by default on every taw-theme site — remove
// this line if this site doesn't need it. Must run before Theme::boot().
TAW\Core\Media\MediaFolders::enable();

// Lucide icon picker (adds the 'icon' Metabox/OptionsPage field type) is
// opt-in per-site — uncomment to enable it for this site:
// TAW\Core\Icons\Lucide::enable();

// Emailit transactional email — routes all wp_mail() calls through
// Emailit's API (form submissions, password resets, WooCommerce, etc.),
// with automatic fallback to plain wp_mail() if unconfigured or the API
// call fails. A paid per-client add-on: requires `composer require
// emailit/emailit-php` here, plus EMAILIT_API_KEY (and optionally
// EMAILIT_FROM_EMAIL / EMAILIT_FROM_NAME) defined in this site's
// wp-config.php — site-specific secrets, never commit them here. The
// defined() guard below keeps this a no-op on every site that doesn't
// define the constant, so it's safe to leave uncommented across all sites.
// if (defined('EMAILIT_API_KEY')) {
//     TAW\Support\EmailConfig::useEmailit(
//         EMAILIT_API_KEY,
//         defined('EMAILIT_FROM_EMAIL') ? EMAILIT_FROM_EMAIL : get_bloginfo('admin_email'),
//         defined('EMAILIT_FROM_NAME') ? EMAILIT_FROM_NAME : ''
//     );
// }

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
