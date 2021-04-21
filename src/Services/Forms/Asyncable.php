<?php

namespace Coretik\Services\Forms;

interface Asyncable
{
    public function public(): bool;
    public function endpoint(): string;
    public function wpAjaxAction(): string;
}
