<?php

declare(strict_types=1);

namespace TAW\Theme\Tests\Unit\Blocks;

use TAW\Blocks\FAQ\FAQ;
use TAW\Theme\Tests\TestCase;

/**
 * Tests only getData()'s hydration logic — see HeroTest's docblock for the
 * full rationale (getMeta()/getRepeater() are mocked, not exercised for
 * real, since they're taw/core's own concern). This block also feeds its
 * $items straight into TAW\Core\Seo\Schema::faqPage() from index.php, so a
 * getData()/registerMetaboxes() field-id drift here would silently break
 * the FAQPage JSON-LD alongside the visible accordion.
 */
final class FAQTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubBlockConstructor();
    }

    public function test_getData_reads_the_expected_field_ids(): void
    {
        $block = $this->getMockBuilder(FAQ::class)
            ->onlyMethods(['getMeta', 'getRepeater'])
            ->getMock();

        $block->method('getMeta')->willReturnMap([
            [42, 'faq_heading', '_taw_', 'Frequently Asked Questions'],
        ]);
        $block->method('getRepeater')->willReturnMap([
            [42, 'faq_items', '_taw_', [
                ['question' => 'What is TAW?', 'answer' => 'A WordPress framework.'],
            ]],
        ]);

        $data = $this->callMethod($block, 'getData', 42);

        $this->assertSame('Frequently Asked Questions', $data['heading']);
        $this->assertSame(
            [['question' => 'What is TAW?', 'answer' => 'A WordPress framework.']],
            $data['items']
        );
    }

    public function test_getData_passes_through_an_empty_repeater_untouched(): void
    {
        $block = $this->getMockBuilder(FAQ::class)
            ->onlyMethods(['getMeta', 'getRepeater'])
            ->getMock();

        $block->method('getMeta')->willReturn('');
        $block->method('getRepeater')->willReturn([]);

        $data = $this->callMethod($block, 'getData', 42);

        $this->assertSame('', $data['heading']);
        $this->assertSame([], $data['items']);
    }
}
