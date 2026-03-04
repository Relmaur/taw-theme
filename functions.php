<?php

/**
 * TAW Theme — Bootstrap
 *
 * WHAT CHANGED (post-split):
 * --------------------------
 * Before: We manually required vite-loader.php and performance.php.
 * After:  The core package's composer.json declares them in the "files"
 *         autoload key, so Composer loads them automatically when
 *         vendor/autoload.php is required. One line does it all.
 *
 * Before: Core classes lived at inc/Core/*, inc/CLI/*, inc/Helpers/*
 * After:  They live at vendor/taw/core/src/* — same namespaces,
 *         different physical location. Composer handles resolution.
 *
 * WHAT STAYS THE SAME:
 * --------------------
 * - All use statements (TAW\Core\BlockLoader, etc.) are identical
 * - The queue-before-render pattern is identical
 * - Block auto-discovery works the same way
 * - Your Blocks/ directory hasn't changed at all
 */

// This single line loads EVERYTHING:
// - Composer's PSR-4 autoloader (resolves TAW\Core\* from vendor/taw/core/src/)
// - The "files" entries from taw/core (vite-loader.php, performance.php)
// - Your TAW\Blocks\* classes from Blocks/

require_once get_template_directory() . '/vendor/autoload.php';

// Theme-specific configuration (this stays in your theme)
require_once get_template_directory() . '/inc/options.php';

// --- Block System ---
TAW\Core\BlockLoader::loadAll();

// --- Visual Editor ---
TAW\Core\VisualEditor::init();

// --- REST API ---
new TAW\Core\Rest\SearchEndpoints();
new TAW\Core\Rest\VisualEditorEndpoint();

// --- Performance Optimizations ---
TAW\Support\Performance::configure([
    // Add external domains your theme connects to
    'preconnect_origins' => [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ],

    // Self-hosted fonts to preload (resolved via vite_asset_url)
    'preload_fonts' => [
        'resources/fonts/Roboto-Regular.woff2',
        'resources/fonts/Roboto-Bold.woff2',
    ],

    // Turn individual features off
    'remove_emoji'       => false,
    'remove_meta_tags'   => true,
    'remove_oembed'      => true,
    'remove_bloat'       => true,
    'preload_hero_image' => true,
]);


// TAW: Register the queue-before-render callback for front-end assets
add_action('wp_enqueue_scripts', [TAW\Core\BlockRegistry::class, 'enqueueQueuedAssets']);

// TAW: Asset Pipeline ---
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

// --- Admin ---
add_action('admin_init', function () {
    remove_post_type_support('page', 'editor');
});
