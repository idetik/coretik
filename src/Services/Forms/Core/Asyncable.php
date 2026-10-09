<?php

namespace Coretik\Services\Forms\Core;

interface Asyncable extends Handlable
{
    public function public(): bool;
    public function endpoint(): string;
    public function wpAjaxAction(): string;
    public function view($data = [], bool $return = false);
}
