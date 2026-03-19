<?php

declare(strict_types=1);

namespace TAW\Blocks\PricingTable;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

class PricingTable extends MetaBlock
{
    protected string $id = 'pricing_table';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'     => 'taw_pricing_table',
            'title'  => __( 'PricingTable Section', 'taw-theme' ),
            'screen' => 'page',
            'fields' => [
                [
                    'id'    => 'pricing_table_heading',
                    'label' => __( 'Heading', 'taw-theme' ),
                    'type'  => 'text',
                ],
            ],
        ]);
    }

    protected function getData(int $postId): array
    {
        return [
            'heading' => $this->getMeta($postId, 'pricing_table_heading'),
        ];
    }
}
