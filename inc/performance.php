<?php

/**
 * TAW Theme — Performance Configuration
 *
 * This is your file. It is never touched by `update-theme`. Returned array
 * is passed to TAW\Core\Theme\Theme::performance() by bootstrapFullSite().
 * See TAW\Support\Performance::configure() (taw/core) for the full option list.
 */

return [
    'preconnect_origins' => [],
    'preload_fonts'      => [],
    'remove_emoji'       => true,
    'remove_meta_tags'   => true,
    'remove_oembed'      => true,
    'remove_bloat'       => true,
    'preload_hero_image' => true,
];
