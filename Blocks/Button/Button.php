<?php

namespace TAW\Blocks\Button;

use TAW\Core\Block\Block;

class Button extends Block
{
    protected string $id = 'button';

    protected function defaults(): array
    {
        return [
            'text'    => '',
            'url'     => '#',
            'variant' => 'primary',  // 'primary', 'secondary', 'ghost'
            'target'  => '_self',
        ];
    }
}