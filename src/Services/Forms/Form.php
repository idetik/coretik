<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Validation\Constraints\Constraint;
use Coretik\Services\Forms\Validation\Validation;
use Coretik\Services\Forms\Validation\Validator;

class Form
{
    protected $id                = ''; //id (=slug) of the form
    protected $template          = ''; //template used to render the form (without .php)
    protected $fields            = []; //form fields with constraints
    protected $validation        = null; //Form Validation object
    protected $submission_result = []; //Form submission result
    protected $view_data         = []; //Data that is passed to form's view()
    protected $default_values    = []; //Default fields values
    protected $display_errors    = true; //Set to false to not display form errors (eg. when we just want to refresh form fields)
    protected $form_name         = null; //Override form name
    protected $metas             = [];

    protected $config;


    public function __construct($id, $values = [], $template = null, $form_name = null, ConfigInterface $config = null, array $metas = [])
    {
        $this->config = $config ?? (new Config());
        $this->id = $id;
        $this->template = $template ?? $id; //Can be overridden by setTemplate() if needed
        $this->form_name = $form_name ?? null;
        $this->setMetas($metas);
        $this->loadFields();
        $this->setDefaultValues($values);
    }

    public function id()
    {
        return $this->id;
    }

    /**
     * Set template name.
     * Use this only if a template name different than form's id is needed.
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function setDisplayErrors($display_errors)
    {
        $this->display_errors = $display_errors;
    }

    public function setMetas(array $data)
    {
        foreach ($data as $key => $value) {
            $this->addMeta($key, $value);
        }
        return $this;
    }

    public function addMeta(string $key, $value)
    {
        $this->metas[$key] = $value;
        return $this;
    }

    public function getMeta(string $key)
    {
        return $this->metas[$key] ?? null;
    }

    /**
     * Load form fields from file
     */
    protected function loadFields()
    {
        $fields_file = $this->config->locator()->locateRules($this->template);
        if (!file_exists($fields_file)) {
            throw new \Exception('Form fields definition file [' . $this->template . '] not found.');
            return;
        }
        $form = $this;
        $fields = include $fields_file;
        if (!is_array($fields)) {
            throw new \Exception('Fields not found in definition file [' . $this->template . '].');
            return;
        }

        //Populate $this->fields from definition file:
        $this->fields = [];
        foreach ($fields as $field_name => $data) {
            if (isset($data['form']) && $data['form'] === false) {
                continue;
            }

            $field = [];
            $field['name'] = $data['name'];
            $field['constraints'] = [];
            if (isset($data['constraints'])) {
                foreach ($data['constraints'] as $key => $args) {
                    $constraint = Constraint::factory($key, $args, $this);
                    if (false !== $constraint) {
                        $field['constraints'][$key] = $constraint;
                        $field['constraints_args'][$key] = $args;
                    }
                }
            }

            if (!Utils::isActionRefresh() && isset($data['constraints_on_submit'])) {
                foreach ($data['constraints_on_submit'] as $key => $args) {
                    $constraint = Constraint::factory($key, $args, $this);
                    if (false !== $constraint) {
                        $field['constraints'][$key] = $constraint;
                        $field['constraints_args'][$key] = $args;
                    }
                }
            }

            $this->fields[$field_name] = $field;

            if (isset($data['default_value'])) {
                $this->setDefaultValues([$field_name => $data['default_value'] ]);
            }
        }
    }

    public function setDefaultValues($values, $replace_all = false)
    {
        if ($replace_all) {
            $this->default_values = [];
        }

        if (!is_array($values)) {
            $values = [];
        }

        foreach ($values as $field => $value) {
            if (isset($this->fields[$field])) {
                $this->default_values[$field] = $value;
            }
        }
    }

    public function getDefaultValues()
    {
        return $this->default_values;
    }

    /**
     * Display or return the whole form's HTML.
     */
    public function view($data = [], $return = false)
    {
        $this->view_data = $data;
        return $this->viewPart($this->template . '/form', $data, $return);
    }

    /**
     * Display or return a form part using the given template
     */
    public function viewPart($template, $data = [], $return = false)
    {
        ob_start();
        $form = $this;
        $data['form_data'] = $this->view_data; //So that main form data can be accessed in form parts
        extract($data);
        include $this->config->locator()->locatePart($template);
        // include locate_template($this->config->get('templateDir') . $template . '.php');
        if ($return) {
            return ob_get_clean();
        } else {
            ob_end_flush();
        }
    }

    public function fieldName($field)
    {
        preg_match_all("/\[[^\]]*\]/", $field, $matches);
        if (!empty($matches[0])) {
            $base = substr($field, 0, strpos($field, '['));
            return $this->getFormName() . '[' . $base . ']' . implode('', $matches[0]);
        }
        return $this->getFormName() . '[' . $field . ']';
    }

    public function fieldLabel($field)
    {
        return !empty($this->fields[$field]['name']) ? $this->fields[$field]['name'] : '';
    }

    public function fieldConstraint($field, $constraint_key, $key = '')
    {
        $constraint = null;

        if (!empty($this->fields[$field]['constraints_args'][$constraint_key])) {
            $constraint_raw = $this->fields[$field]['constraints_args'][$constraint_key];
            if (!empty($key)) {
                if (!empty($constraint_raw[$key])) {
                    $constraint = $constraint_raw[$key];
                }
            } else {
                $constraint = $constraint_raw;
            }
        }

        return $constraint;
    }

    public function getFormName()
    {
        return $this->form_name ?? $this->config->getFormPrefix() . '-form-' . $this->id;
    }

    public function setFormName($name)
    {
        return $this->form_name = $name;
    }

    /**
     * Open some private attributes to public access (for convenience in templates):
     */
    public function __get($key)
    {
        $value = null;
        if (in_array($key, ['id'])) {
            $value = $this->{$key};
        }
        return $value;
    }

    public function setValue($field, $val)
    {
        if (!$this->isValidating()) {
            return false;
        }

        if (empty($this->fields[$field])) {
            return false;
        }

        $data = $this->validation->getData();
        $data[$field] = $val;
        $this->validation->setData($data);
        return true;
    }

    public function getValue($field, $default = '')
    {
        $bracket = strpos($field, '[');
        if (false !== $bracket) {
            preg_match_all("/\[([^\]]+)\]/", $field, $matches);
            $field = substr($field, 0, $bracket);
        }

        if ($this->isValidating()) {
            $data = $this->validation->getData();
            if (isset($data[$field])) {
                $data = $data[$field];
                if (!empty($matches[1])) {
                    for ($i = 0; $i < count($matches); $i++) {
                        if (!isset($matches[1][$i])) {
                            continue;
                        }
                        if (!isset($data[$matches[1][$i]])) {
                            return $default;
                        }
                        $data = $data[$matches[1][$i]];
                    }
                }
                return is_array($data) ? $data : esc_attr($data);
            }
        } else if ($this->isSubmitting()) {
            if (isset($_POST[$this->getFormName()][$field])) {
                $data = $_POST[$this->getFormName()][$field];
                if (!empty($matches[1])) {
                    for ($i = 0; $i < count($matches); $i++) {
                        if (!isset($matches[1][$i])) {
                            continue;
                        }
                        if (!isset($data[$matches[1][$i]])) {
                            return $default;
                        }
                        $data = $data[$matches[1][$i]];
                    }
                }
                return is_array($data) ? $data : esc_attr($data);
            }
        } else {
            if (isset($this->default_values[$field])) {
                $data = $this->default_values[$field];
                if (!empty($matches[1])) {
                    for ($i = 0; $i < count($matches); $i++) {
                        if (!isset($matches[1][$i])) {
                            continue;
                        }
                        if (!isset($data[$matches[1][$i]])) {
                            return $default;
                        }
                        $data = $data[$matches[1][$i]];
                    }
                }
                return is_array($data) ? $data : esc_attr($data);
            }
        }
        return $default;
    }

    public function getValues()
    {
        return $this->isValidating() ? $this->validation->getData() : [];
    }

    protected function getActionUrl()
    {
        return get_permalink();
    }

    public function nonceField()
    {
        wp_nonce_field($this->config->getFormPrefix() . '_form_submit_' . $this->id, $this->fieldName('nonce'));
    }

    public function spamField()
    {
        $this->viewPart('fields/spam');
    }

    protected function checkNonce()
    {
        return isset($_POST[$this->getFormName()]['nonce']) && wp_verify_nonce($_POST[$this->getFormName()]['nonce'], $this->config->getFormPrefix() . '_form_submit_' . $this->id);
    }

    public function process()
    {
        if ($this->isSubmitting()) {
            $this->processPostedData();
        }
    }

    public function isSubmitting()
    {
        return isset($_POST[$this->getFormName()]);
    }

    public function setError(string $fieldName, string $message = '&nbsp')
    {
        $this->validation->forceFieldError($fieldName, $message);
        return $this;
    }

    public function hasErrors()
    {
        //To not display errors, make as if there were none:
        if (!$this->display_errors) {
            return false;
        }

        $submitted_ok = $this->isSubmitting() && !empty($this->submission_result['ok']);
        $validated_ok = $this->isValidating() && empty($this->validation->getErrors());
        return $this->isSubmitting() && ( !$submitted_ok || !$validated_ok );
    }

    public function submittedOk()
    {
        return $this->isSubmitting() && !$this->hasErrors() && !$this->isSpam();
    }

    public function errorClass($fields)
    {
        $error_class = '';
        if ($this->isValidating() && $this->hasErrors()) {
            $errors = $this->validation->getErrors();
            $fields = Utils::forceArray($fields);
            foreach ($fields as $field_name) {
                if (isset($errors[$field_name])) {
                    $error_class = $this->config->getCssErrorClass();
                    break;
                }
            }
        }
        return $error_class;
    }

    public function getErrorMessage($fields)
    {
        if ($this->isValidating() && $this->hasErrors()) {
            $errors = $this->validation->getErrors();
            $fields = Utils::forceArray($fields);
            foreach ($fields as $field_name) {
                if (isset($errors[$field_name]) && !empty($errors[$field_name])) {
                    return current($errors[$field_name])->getMessage();
                }
            }
        }
        return '';
    }

    public function getErrors()
    {
        if ($this->isValidating() && $this->hasErrors()) {
            return $this->validation->getErrors();
        }
        return [];
    }

    public function getErrorDebug()
    {
        $error = '';
        if ($this->hasErrors() && isset($this->submission_result['error'])) {
            $error = $this->submission_result['error'];
        }
        return $error;
    }

    public function processPostedData()
    {
        $result = [ 'ok' => false, 'error' => '', 'data' => [], 'validation_errors' => [] ];

        $form_name = $this->getFormName();

        if (!isset($_POST[$form_name])) {
            $result['error'] = 'No form found';
            $this->submission_result = $result;
            return $result;
        }

        $posted_data = $_POST[$form_name];

        if ($this->isSpam()) {
            $result['error'] = 'Honeypot field was filled';
            $this->submission_result = $result;
            sleep(10);
            $this->setDisplayErrors(false);
            return $result;
        }
        if (!$this->checkNonce()) {
            $result['error'] = 'Wrong / empty nonce';
            $this->submission_result = $result;
            sleep(10);
            $this->setDisplayErrors(false);
            return $result;
        }
        if (!isset($posted_data['form_id'])) {
            $result['error'] = 'No form id';
            $this->submission_result = $result;
            return $result;
        }

        $form_id = $posted_data['form_id'];
        if ($form_id !== $this->id) {
            $result['error'] = 'Wrong form id';
            $this->submission_result = $result;
            return $result;
        }

        $posted_data = $this->sanitizeForm($posted_data);
        $validation_result = $this->validate($posted_data);

        if ($validation_result['is_valid']) {
            $result['ok'] = true;
            $result['data'] = $validation_result['data'];
            do_action('coretik/forms/valid_form_submitted', $form_id, $posted_data, $result['data']);
        } else {
            $result['error'] = 'Validation failed';
            $result['validation_errors'] = $validation_result['errors'];
        }

        $this->submission_result = $result;
    }

    public function getSubmissionResult()
    {
        return $this->submission_result;
    }

    protected function sanitizeForm($posted)
    {
        $data = [];
        unset($posted['form_id']);
        unset($posted['nonce']);
        foreach ($posted as $key => $value) {
            $data[sanitize_key($key)] = Utils::sanitizeFormField($value);
        }
        return $data;
    }

    public function validate($posted)
    {
        $this->validation = new Validation();

        if (empty($posted)) {
            //If for example there are only checkboxes in fields, nothing is posted...
            //Add the fields to $posted array (as empty values) or validation will not run:
            foreach ($this->fields as $field_key => $data) {
                $posted[$field_key] = '';
            }
        }

        $this->validation->setData($posted);

        foreach ($this->fields as $field_key => $data) {
            $validator  = new Validator();
            foreach ($data['constraints'] as $constraint) {
                $validator->addConstraint($constraint);
            }
            $this->validation->setValidator($field_key, $validator);
        }

        $result = [
            'is_valid' => $this->validation->run(),
            'data'     => $this->validation->getData(),
            'errors'   => $this->validation->getErrors()
        ];

        return $result;
    }

    public function getValidation()
    {
        return $this->validation;
    }

    public function isValidating()
    {
        return !empty($this->validation);
    }

    public function isSpam()
    {
        if (is_user_logged_in()) {
            return false;
        } else {
            return isset($_POST['form_coretik_confirm']) && in_array($_POST['form_coretik_confirm'], ['on', true, 'true', 1, '1']);
        }
    }

    public function fieldExists($fieldname)
    {
        return isset($this->fields[$fieldname]);
    }

    public function reset()
    {
        unset($_POST[$this->getFormName()]);
        $this->validation = null;
        return $this;
    }

    public function triggerJsEvent($event, $data = [])
    {
        $query = sprintf(
            "$('[data-form-id=\"%s\"]').trigger('%s', %s);",
            $this->id,
            $event,
            json_encode($data)
        );

        if (\wp_doing_ajax()) {
            printf('<script type="text/javascript">jQuery(function($) {%s});</script>', $query);
        } else {
            \add_action('wp_footer', function () use ($query) {
                printf('<script type="text/javascript">jQuery(function($) {%s});</script>', $query);
            }, 99);
        }
    }
}
