<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Models\Wp\PostModel;

class ProductModel extends PostModel
{
    protected function initializeModel(): void
    {
        $this->declareMetas([
            'price' => 'price',
            'stock' => 'stock',
            'secret' => '_secret',
        ]);
        $this->metaDefinition('secret')->protect();
    }
}
