<?php

namespace Coretik\Core\Query\Interfaces;

interface QuerierInterface
{
    public function getQueryArgsDefault();
    public function newQueryBuilderInstance();
    public function results(): array;
}
