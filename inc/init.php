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
 *
 *   Actions:
 *     taw_init                — Fires after all TAW core is bootstrapped
 */

require_once get_template_directory() . '/vendor/autoload.php';

// --- Block System ---
TAW\Core\BlockLoader::loadAll();

// --- Visual Editor ---
TAW\Core\VisualEditor::init();

// --- REST API ---
new TAW\Core\Rest\SearchEndpoints();
new TAW\Core\Rest\VisualEditorEndpoint();

// --- Asset Pipeline ---
add_action('wp_enqueue_scripts', [TAW\Core\BlockRegistry::class, 'enqueueQueuedAssets']);

add_action('wp_enqueue_scripts', function () {
    vite_enqueue_theme_assets();
});

// --- Admin ---


// --- Performance ---
// Wrapped in after_setup_theme so filters registered in functions.php are in place
// before apply_filters() runs. Priority 5 ensures this fires before the theme's
// own after_setup_theme callback (priority 10) and before any wp_head hooks.
add_action('after_setup_theme', function () {
    TAW\Support\Performance::configure(
        apply_filters('taw_performance_config', [
            'preconnect_origins' => [],
            'preload_fonts'      => [],
            'remove_emoji'       => true,
            'remove_meta_tags'   => true,
            'remove_oembed'      => true,
            'remove_bloat'       => true,
            'preload_hero_image' => true,
        ])
    );
}, 5);

// --- Theme Setup ---


// --- Ready ---
do_action('taw_init');
