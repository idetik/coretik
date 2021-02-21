<?php

namespace Coretik\Core\Actions;

interface ActionFrontInterface extends ActionInterface
{
    public function onError(Exception $error);
}
