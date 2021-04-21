<?php

namespace Coretik\Services\Forms;

interface Handlable
{
    public function getName();
    public function isRunnable();
    public function process();
}
