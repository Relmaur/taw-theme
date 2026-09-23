<?php

declare(strict_types=1);

namespace ReactiphTawDemo;

use Reactiph\Component\BaseComponent;

/**
 * Deliberately does not override template() -- loads
 * CounterComponent.reactiph.html from this same folder instead (ADR
 * 0020's folder-based component convention), demonstrating that it
 * composes naturally with TAW's own folder-based block convention
 * (Counter.php right next to it).
 */
final class CounterComponent extends BaseComponent
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}
