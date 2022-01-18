<?php

namespace Coretik\Services\Forms;

abstract class BaseForm implements Handlable
{
    protected $form_id;
    protected $form_values;
    protected $template;
    protected $form_name;
    protected $form;
    protected $data = [];
    protected $config;
    protected $initialized = false;

    public function __construct(string $id, array $values = [], $template = null, $form_name = null, ConfigInterface $config = null)
    {
        $this->form_id = $id;
        $this->form_values = $values;
        $this->template = $template;
        $this->form_name = $form_name;
        $this->config = $config;
    }

    abstract protected function isValidContext(): bool;
    abstract protected function run(): void;

    public function setConfig(ConfigInterface $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function setConfigIfNotDefined(ConfigInterface $config): self
    {
        if (!$this->hasConfig()) {
            $this->setConfig($config);
        }
        return $this;
    }

    protected function initializeIfNot()
    {
        if (!$this->initialized) {
            if (method_exists($this, 'initialize')) {
                $this->initialize();
                $this->initialized = true;
            }
        }
    }

    public function hasConfig(): bool
    {
        return !empty($this->config);
    }

    public function getName(): string
    {
        return $this->form_id;
    }

    public function isRunnable(): bool
    {
        if (!$this->isValidContext()) {
            return false;
        }

        $this->initializeIfNot();

        if (!$this->form()->isSubmitting()) {
            return false;
        }

        return true;
    }

    public function process()
    {
        $this->form()->process();
        if ($this->form()->submittedOk()) {
            try {
                $this->run();
                if (\method_exists($this, 'onSuccess')) {
                    $this->onSuccess();
                }
            } catch (Exception $e) {
                if (!empty($e->getMessage())) {
                    $this->data['errors'][] = $e->getMessage();
                }
                $this->onProcessError($e);
            }
        } else {
            $this->onValidationError();
        }
    }

    protected function onValidationError()
    {
    }
    protected function onProcessError(Exception $e)
    {
    }

    public function view($data = [])
    {
        $data = array_merge($data, $this->data);
        $this->form()->view($data);
    }

    public function defaultValues()
    {
        return $this->form_values;
    }

    public function form(): Form
    {
        if (is_null($this->form)) {
            $this->initializeIfNot();
            $this->form = new Form($this->form_id, $this->defaultValues(), $this->template, $this->form_name, $this->config);
        }
        return $this->form;
    }

    protected function humanize(string $field): string
    {
        switch ($field) {
            default:
                $label = $this->form()->fieldLabel($field);
                return !empty($label) ? $label : $field;
        }
    }
}
