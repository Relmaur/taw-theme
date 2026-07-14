<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for taw-theme's block unit test suite.
 *
 * Mirrors taw-core's own tests/bootstrap.php (vendor/taw/core/tests/bootstrap.php):
 * this suite runs WITHOUT a real WordPress install — Brain Monkey mocks individual
 * WP functions per-test instead. That's a deliberate division of labor with
 * bin/ci/smoke-test.php, which boots a real WordPress + MySQL environment and
 * exercises the full render path: this suite is for fast, isolated tests of a
 * block's own getData() hydration logic, the smoke test is for "does this
 * actually render end-to-end against a real site."
 *
 * TAW\Core\Block\BaseBlock's constructor calls get_template_directory_uri() and
 * get_template_directory() unconditionally, and MetaBlock's constructor also
 * calls add_action('init', ...) — every file under vendor/taw/core/src guards
 * itself with `if (!defined('ABSPATH')) exit;`, so ABSPATH must be defined
 * before any TAW class is autoloaded, or the PHP process exits immediately.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

require __DIR__ . '/../vendor/autoload.php';
