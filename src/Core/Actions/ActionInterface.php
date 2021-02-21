<?php

namespace Coretik\Core\Actions;

interface ActionInterface
{
    public function getRequired();
    public function run($data);
}
