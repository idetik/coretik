<?php

namespace Coretik\Services\Modals;

interface ModalInterface
{
    public function id();
    public function isOpen();
    public function render();
}
