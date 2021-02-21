<?php

namespace Coretik\Core\Builders\Interfaces;

use Coretik\Core\Models\Model;
use Coretik\Core\Models\Querier;

interface TaxonomiableInterface
{
    public function addTaxonomy(BuilderInterface $taxonomy);
    public function taxonomies(): array;
}
