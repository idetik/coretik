<?php

namespace Coretik\Services\Forms\Core;

interface Asyncable
{
    public function public(): bool;
    public function endpoint(): string;
    public function wpAjaxAction(): string;
}
