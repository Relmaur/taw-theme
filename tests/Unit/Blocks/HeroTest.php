<?php

declare(strict_types=1);

namespace TAW\Theme\Tests\Unit\Blocks;

use TAW\Blocks\Hero\Hero;
use TAW\Theme\Tests\TestCase;

/**
 * Reference example for the make-metablock / figma-to-block skills' required
 * test-generation step (see .claude/skills/make-metablock/SKILL.md § "Write
 * the block's unit test"). Tests only getData()'s hydration logic — the array
 * it assembles from meta values — not rendering or metabox registration,
 * which need a real WordPress install and are covered instead by
 * bin/ci/smoke-test.php.
 *
 * getMeta() is mocked rather than exercised for real: it's a thin wrapper
 * around TAW\Core\Metabox\Metabox's own static methods, which are already
 * covered by taw-core's own test suite. Mocking it keeps this test scoped to
 * what's actually this block's own logic — does getData() ask for the right
 * field IDs and assemble them into the right shape — and catches a
 * getData()/registerMetaboxes() field-id mismatch (a real class of
 * regression: rename a field in one method, forget the other) without
 * needing WordPress, a database, or taw/core's internals at all.
 */
final class HeroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubBlockConstructor();
    }

    public function test_getData_reads_the_expected_field_ids(): void
    {
        $block = $this->getMockBuilder(Hero::class)
            ->onlyMethods(['getMeta'])
            ->getMock();

        $block->method('getMeta')->willReturnMap([
            [42, 'hero_heading', '_taw_', 'Lorem ipsum dolor sit amet'],
            [42, 'hero_tagline', '_taw_', 'Consectetur adipiscing elit'],
            [42, 'hero_image', '_taw_', 17],
            [42, 'hero_cta_text', '_taw_', 'Get Started'],
            [42, 'hero_cta_url', '_taw_', 'https://example.test/start'],
        ]);

        $data = $this->callMethod($block, 'getData', 42);

        $this->assertSame('Lorem ipsum dolor sit amet', $data['heading']);
        $this->assertSame('Consectetur adipiscing elit', $data['tagline']);
        $this->assertSame(17, $data['image_id']);
        $this->assertSame('Get Started', $data['cta_text']);
        $this->assertSame('https://example.test/start', $data['cta_url']);
    }

    public function test_getData_passes_through_empty_fields_untouched(): void
    {
        $block = $this->getMockBuilder(Hero::class)
            ->onlyMethods(['getMeta'])
            ->getMock();

        $block->method('getMeta')->willReturn('');

        $data = $this->callMethod($block, 'getData', 42);

        $this->assertSame('', $data['heading']);
        $this->assertSame('', $data['cta_url']);
    }
}
