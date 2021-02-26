<?php

namespace Coretik\Services\Notices\Iterators;

class FilterValidIterator extends \FilterIterator
{
    public function __construct(\ArrayIterator $iterator)
    {
        parent::__construct($iterator);
    }
   
    public function accept()
    {
        $notice = $this->getInnerIterator()->current();
        return $notice->waiting();
    }
}
