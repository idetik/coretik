<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\DictionnaryInterface;

class TermDictionnary extends Collection implements DictionnaryInterface
{
    public function __construct()
    {
        parent::__construct([
            'alias_of', //(string) Slug of the term to make this term an alias of. Default empty string. Accepts a term slug.
            'description',
            'parent',
            'slug',
        ]);
    }
}
