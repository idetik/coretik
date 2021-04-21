<?php

namespace Coretik\Services\Forms;

class Config
{
    protected $templateDir = 'templates/forms/';
    protected $formFile = 'form.php';
    protected $formRulesFile = 'rules.php';
    protected $formPrefix = 'coretik';
    protected $cssClassError = 'form-group-error';

    public function __construct(array $conf = [])
    {
        $this->setMultiple($conf);
    }

    public function setMultiple(array $conf)
    {
        foreach ($conf as $key => $val) {
            $this->set($key, $val);
        }
    }

    public function set(string $key, $val)
    {
        if (\property_exists($this, $key)) {
            $this->$key = $val;
        }
    }

    public function get(string $key)
    {
        return $this->$key ?? null;
    }
}
