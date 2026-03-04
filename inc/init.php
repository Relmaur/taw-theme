<?php

/**
 * TAW Theme — Core Init
 *
 * This file is maintained by the theme author and updated with each theme release.
 * Do NOT modify it directly — use the filters and actions below in functions.php instead.
 *
 * Available hooks for developer customisation:
 *
 *   Filters:
 *     taw_performance_config  — (array) Full Performance::configure() config array
 *     taw_nav_menus           — (array) Nav menu locations passed to register_nav_menus()
 *     taw_custom_logo         — (array|false) custom-logo theme support args, or false to disable
 *
 *   Actions:
 *     taw_init                — Fires after all TAW core is bootstrapped
 *     taw_after_theme_setup   — Fires at the end of the after_setup_theme callback
 *     taw_after_admin_init    — Fires at the end of the admin_init callback
 */

require_once get_template_directory() . '/vendor/autoload.php';

// --- Block System ---
TAW\Core\BlockLoader::loadAll();

// --- Visual Editor ---
TAW\Core\VisualEditor::init();

// --- REST API ---
new TAW\Core\Rest\SearchEndpoints();
new TAW\Core\Rest\VisualEditorEndpoint();

// --- Performance ---
TAW\Support\Performance::configure(
    apply_filters('taw_performance_config', [
        'preconnect_origins' => [],
        'preload_fonts'      => [],
        'remove_emoji'       => false,
        'remove_meta_tags'   => true,
        'remove_oembed'      => true,
        'remove_bloat'       => true,
        'preload_hero_image' => true,
    ])
);

// --- Asset Pipeline ---
add_action('wp_enqueue_scripts', [TAW\Core\BlockRegistry::class, 'enqueueQueuedAssets']);
add_action('wp_enqueue_scripts', function () {
    vite_enqueue_theme_assets();
});

// --- Theme Setup ---
add_action('after_setup_theme', function () {
    load_theme_textdomain('taw-theme', get_template_directory() . '/languages');

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

    $logo_args = apply_filters('taw_custom_logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    if ($logo_args !== false) {
        add_theme_support('custom-logo', $logo_args);
    }

    register_nav_menus(
        apply_filters('taw_nav_menus', [
            'primary' => __('Primary Menu', 'taw-theme'),
            'footer'  => __('Footer Menu', 'taw-theme'),
        ])
    );

    do_action('taw_after_theme_setup');
});

// --- Admin ---
add_action('admin_init', function () {
    remove_post_type_support('page', 'editor');

    do_action('taw_after_admin_init');
});

// --- Ready ---
do_action('taw_init');
