<?php

/**
 * TAW Theme — Framework Bootstrap
 *
 * This file is 100% framework-owned and blindly overwritten by
 * `update-theme` on every sync — no merge, no shared git history
 * required, and nothing here should ever be hand-edited.
 *
 * Site-specific configuration lives in three files instead, none of
 * which `update-theme` ever touches:
 *
 *   inc/options.php        — OptionsPage field config
 *   inc/performance.php    — performance() config array
 *   inc/customizations.php — theme supports, nav menus, and any other hooks
 *
 * All three are loaded automatically by bootstrapFullSite() below if present.
 */

require_once get_template_directory() . '/vendor/autoload.php';

TAW\Core\Theme\Theme::bootstrapFullSite(get_template_directory());
