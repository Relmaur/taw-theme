<?php

declare(strict_types=1);

/**
 * ADR 0021 smoke-test artifact: a real TAW MetaBlock, auto-discoverable
 * by TAW's own BlockLoader with zero taw-core changes (ReactiveMetaBlock
 * extends MetaBlock). Not runnable standalone -- like
 * examples/wordpress-plugin/, this is meant to be copied or symlinked
 * into a real TAW theme's Blocks/ directory (as Blocks/Counter/) to
 * verify against a real WordPress + TAW site.
 *
 * The class name and namespace follow TAW\Blocks\BlockLoader's own
 * derivation rule exactly: Blocks/Counter/Counter.php -> TAW\Blocks\Counter\Counter.
 *
 * CounterComponent.php is require_once'd directly rather than relied on
 * to autoload via its own ReactiphTawDemo namespace -- this folder is
 * meant to be copied or symlinked as a whole into an arbitrary theme's
 * Blocks/ directory, which has no reason to already have a PSR-4 mapping
 * for that namespace. require_once keeps the folder self-contained
 * regardless of the target theme's own autoload configuration.
 */

namespace TAW\Blocks\Counter;

use Reactiph\TawBridge\ReactiveMetaBlock;
use ReactiphTawDemo\CounterComponent;

require_once __DIR__ . '/CounterComponent.php';

final class Counter extends ReactiveMetaBlock
{
    protected string $id = 'reactiph-counter';

    protected function registerMetaboxes(): void
    {
        // No fields for this demo -- a real block would call Metabox::add(...)
        // here, exactly as any other TAW MetaBlock does, to let editors set
        // the starting count from wp-admin.
    }

    protected function getData(int|false $postId): array
    {
        return ['count' => 3];
    }

    protected function componentClass(): string
    {
        return CounterComponent::class;
    }
}
