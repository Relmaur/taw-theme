<?php

/**
 * TAW Theme — Developer Customisations
 *
 * This is your file. Use the hooks below to configure the theme.
 * Core bootstrap lives in inc/init.php — update the theme to get the latest version.
 */

require_once get_template_directory() . '/inc/init.php';
require_once get_template_directory() . '/inc/options.php';

// ---------------------------------------------------------------------------
// Performance
// Docs: https://github.com/Relmaur/taw-core
// ---------------------------------------------------------------------------
add_filter('taw_performance_config', function (array $config): array {
    $config['preconnect_origins'] = [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ];

    $config['preload_fonts'] = [
        'resources/fonts/Roboto-Regular.woff2',
        'resources/fonts/Roboto-Bold.woff2',
    ];

    return $config;
});

// ---------------------------------------------------------------------------
// Navigation Menus
// Add extra locations here, or override the defaults entirely.
// ---------------------------------------------------------------------------
// add_filter('taw_nav_menus', function (array $menus): array {
//     $menus['mobile'] = __('Mobile Menu', 'taw-theme');
//     return $menus;
// });

// ---------------------------------------------------------------------------
// Custom Logo
// Return false to disable custom-logo theme support.
// ---------------------------------------------------------------------------
// add_filter('taw_custom_logo', function (array $args): array {
//     $args['width'] = 300;
//     return $args;
// });

// ---------------------------------------------------------------------------
// Runs after all TAW core is bootstrapped (BlockLoader, REST, Performance…)
// ---------------------------------------------------------------------------
// add_action('taw_init', function () {
//     // e.g. register custom post types, taxonomies, etc.
// });

// ---------------------------------------------------------------------------
// Runs at the end of after_setup_theme
// ---------------------------------------------------------------------------
// add_action('taw_after_theme_setup', function () {
//     add_theme_support('woocommerce');
// });

// ---------------------------------------------------------------------------
// Runs at the end of admin_init
// ---------------------------------------------------------------------------
// add_action('taw_after_admin_init', function () {
//     // e.g. remove post type support from other post types
// });
