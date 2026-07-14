<?php

declare(strict_types=1);

namespace TAW\Theme\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for taw-theme's block unit suite — wires up Brain Monkey so
 * every test can stub individual WP functions instead of needing a real
 * WordPress install. Every test class in tests/Unit/Blocks/ should extend this.
 *
 * Mirrors taw-core's own tests/TestCase.php (vendor/taw/core/tests/TestCase.php);
 * kept as a separate copy here rather than a shared dependency because test
 * infrastructure isn't part of taw/core's public runtime API.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Stub the WP functions every Block/MetaBlock constructor calls
     * unconditionally (TAW\Core\Block\BaseBlock::__construct() calls
     * get_template_directory()/get_template_directory_uri(); MetaBlock's
     * constructor additionally calls add_action('init', ...)). Call this in
     * setUp() before instantiating any block under test — without it,
     * `new SomeBlock()` fatals on an undefined function.
     */
    protected function stubBlockConstructor(): void
    {
        Functions\when('get_template_directory')->justReturn('/tmp/theme');
        Functions\when('get_template_directory_uri')->justReturn('https://example.test/wp-content/themes/taw-theme');
        Functions\when('add_action')->justReturn(true);
    }

    /**
     * Reach into a private/protected method for direct testing — used to call
     * a block's protected getData(int|false $postId): array without widening
     * its real API surface just to make it testable.
     *
     * @param object $object
     * @param mixed  ...$args
     * @return mixed
     */
    protected function callMethod(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invoke($object, ...$args);
    }
}
