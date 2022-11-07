<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Core\Validation\Constraints\Constraint;
use Coretik\Services\Forms\Core\Validation\Validation;
use Coretik\Services\Forms\Core\Validation\Validator;
use Coretik\Services\Forms\Core\Handlable;
use Coretik\Services\Forms\Core\Utils;
use Coretik\Services\Forms\Core\Exception;
use Coretik\Services\Forms\Core\ConfigInterface;
use Coretik\Services\Forms\Core\Container as Forms;

abstract class Form implements Handlable
{
    protected $initialized       = false;
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
    protected $persistentMetaKey = [];
    protected static $instances = [];
    protected $context = [];

    protected $akismet_is_spam = null;
    protected $config;

    public function __construct(string $id, array $values = [], $template = null, $form_name = null, ?ConfigInterface $config = null, array $metas = [])
    {
        $this->config = $config ?? (new Config());
        $this->id = $id;
        $this->template = $template ?? $id; //Can be overridden by setTemplate() if needed
        $this->form_name = $form_name;
        $this->setMetas($metas);
        $this->loadFields();
        $this->setDefaultValues($values);
    }

    abstract public function getRules(): array;
    abstract protected function isValidContext(): bool;
    abstract protected function run(): void;

    public function id()
    {
        return $this->id;
    }

    public function hasConfig(): bool
    {
        return !empty($this->config);
    }

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

    // run before view or before process
    protected function initializeIfNot()
    {
        if (!$this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }
    }

    public function isRunnable(): bool
    {
        if (!$this->isValidContext()) {
            return false;
        }

        $this->initializeIfNot();

        if (!$this->isSubmitting()) {
            return false;
        }

        return true;
    }

    protected function humanize(string $field): string
    {
        switch ($field) {
            default:
                $label = $this->fieldLabel($field);
                return !empty($label) ? $label : $field;
        }
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

    // Metas for templating helpers
    public function setMetas(array $data, $persistent = false)
    {
        foreach ($data as $key => $value) {
            $this->addMeta($key, $value, $persistent);
        }
        return $this;
    }

    public function addMeta(string $key, $value, $persistent = false)
    {
        $this->metas[$key] = $value;
        if ($persistent) {
            $this->persistentMetaKey[] = $key;
        }
        return $this;
    }

    public function getMeta(string $key)
    {
        $this->initializeIfNot();

        if (array_key_exists($key, $this->metas)) {
            $meta = $this->metas[$key];
            if (\is_callable($meta)) {
                $meta = \call_user_func($meta);
            }
        }

        return $meta ?? (\array_key_exists('persistent-data', $_REQUEST) ? \esc_attr(\wp_unslash($_REQUEST['persistent-data'][$key])) : null);
    }

    /**
     * Load form fields from file
     */
    protected function loadFields()
    {
        $form = $this;
        $fields = $this->getRules();
        if (!is_array($fields)) {
            throw new \Exception('Fields not found in definition class [' . __CLASS__ . '].');
            return;
        }

        //Populate $this->fields from definition file:
        $this->fields = [];
        foreach ($fields as $field_name => $data) {
            if (isset($data['form']) && $data['form'] === false) {
                continue;
            }

            $has_email_constraint = false;
            $field = [];
            $field['name'] = $data['name'];
            $field['constraints'] = [];
            if (isset($data['constraints'])) {
                foreach ($data['constraints'] as $key => $args) {
                    if ('email' === $key) {
                        $has_email_constraint = true;
                    }
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

            if (!empty($_GET) && array_key_exists($field_name, $_GET)) {
                $prefilled_value = esc_attr($_GET[$field_name]);

                if ($has_email_constraint) {
                    $prefilled_value = Utils::formNormalizeSpaces($prefilled_value);
                    $prefilled_value = str_replace(" ", "+", $prefilled_value);
                    $prefilled_value = sanitize_email($prefilled_value);
                } else {
                    $prefilled_value = Utils::formSanitizeText($prefilled_value);
                }

                $this->setDefaultValue($field_name, $prefilled_value);

            } elseif (isset($data['default_value'])) {
                $this->setDefaultValue($field_name, $data['default_value']);
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
            $this->setDefaultValue($field, $value);
        }
    }

    public function setDefaultValue($field, $value)
    {
        if (isset($this->fields[$field])) {
            $this->default_values[$field] = $value;
        }
        return $this;
    }

    public function hasDefaultValue($field)
    {
        return isset($this->default_values[$field]);
    }

    public function getDefaultValue($field)
    {
        return $this->default_values[$field] ?? null;
    }

    /**
     * Display or return the whole form's HTML.
     */
    public function view($data = [], bool $return = false)
    {
        $this->initializeIfNot();
        $this->view_data = $data;

        if (!$this->isSubmitting() && !$this->isRefreshing()) {
            \do_action('coretik/form/first-view', $this->id(), $this);
            \do_action('coretik/form/' . $this->id() . '/first-view', $this);
        }

        return $this->includeTemplatePart(
            $this->config->locator()->locateTemplate($this->template),
            $data,
            $return
        );
    }

    /**
     * Display or return a form part using the given template
     */
    public function viewPart($part, array $data = [], bool $return = false)
    {
        return $this->includeTemplatePart(
            $this->config->locator()->locatePart($part),
            $data,
            $return
        );
    }

    protected function includeTemplatePart($file, array $data = [], bool $return = false)
    {
        ob_start();
        $form = $this;
        $data['form_data'] = $this->view_data; //So that main form data can be accessed in form parts
        extract($data);
        include $file;
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

    public function setValue($field, $val)
    {
        if ($this->isRefreshing()) {
            $_POST[$this->getFormName()][$field] = $val;
            return true;
        }

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
            if ($this->hasDefaultValue($field)) {
                $data = $this->getDefaultValue($field);
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
        return \get_permalink();
    }

    public function nonceField()
    {
        \wp_nonce_field($this->config->getFormPrefix() . '_form_submit_' . $this->id, $this->fieldName('nonce'));
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

        if (Utils::isActionRefresh()) {
            return;
        }

        if ($this->submittedOk()) {
            try {
                $this->run();
                $this->onSuccess();
            } catch (Exception $e) {
                if (!empty($e->getMessage())) {
                    $this->view_data['errors'][] = $e->getMessage();
                }
                $this->onProcessingError($e);
                $this->onError();
            }
        } else {
            $this->onValidationError();
            $this->onError();
        }
    }

    // Trigger before form view or before form process
    protected function initialize() {}

    // Trigger on form success
    protected function onSuccess() {}

    // Error trigger when validating local rules
    protected function onValidationError() {}

    // Error trigger during form processing
    protected function onProcessingError(Exception $e) {}

    // Error trigger on any form error
    protected function onError() {}

    public function isRefreshing()
    {
        return Utils::isActionRefresh();
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
            $result['error'] = 'Anti-robots spam validation failed';
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

        if ($this->isRefreshing()) {
            return ['ok' => true];
        }

        $posted_data = $this->sanitizeForm($posted_data);
        $validation_result = $this->validate($posted_data);

        if ($validation_result['is_valid']) {
            $result['ok'] = true;
            $result['data'] = $validation_result['data'];
            do_action('ifocop/forms/valid_form_submitted', $form_id, $posted_data, $result['data']);
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
        $this->validation = new Validation($this);

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
        if($this->isRefreshing()) {
            return false;
        }

        if (is_user_logged_in()) {
            return false;
        } else {
            return isset($_POST['form_coretik_confirm']) && in_array($_POST['form_coretik_confirm'], ['on', true, 'true', 1, '1']);
        }

        return $this->honeyPotChecked()
            || $this->hasWordsInBlacklist($_POST[$this->getFormName()]);
    }

    public function honeyPotChecked()
    {
        return isset($_POST['form_coretik_confirm']) && in_array($_POST['form_coretik_confirm'], ['on', true, 'true', 1, '1']);
    }

    public function hasWordsInBlacklist($fields) {
        if (!is_string($fields) && !is_array($fields)) {
            return false;
        }

        if (is_string($fields)) {
            $fields = [$fields];
        }

        $blacklist = $this->getWordsInBlacklist();

        foreach ($fields as $string) {
            if (is_string($string) && !empty($string)) {
                $string = mb_strtolower($string);
                $string = remove_accents($string);

                foreach ($blacklist as $word) {
                    if (false !== mb_strripos($string, $word)) {
                        return true;
                    }
                }
            }
        }

        return false;
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

    protected function redirectTo(string $url, int $status = 302): void
    {
        \wp_safe_redirect($url, $status, '');
        exit;
    }

    public function embedToUrl(string $url)
    {
        $trackingParameters = [];

        foreach($this->getValues() as $key => $value) {
            if(is_string($value) && mb_strlen($value) <= 100) {
                $trackingParameters[$key] = $value;
            }
        }

        return \add_query_arg($trackingParameters, $url);
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
            printf('<script>jQuery(function($) {%s});</script>', $query);
        } else {
            \add_action('wp_footer', function () use ($query) {
                printf('<script>jQuery(function($) {%s});</script>', $query);
            }, 99);
        }
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

    public function getWordsInBlacklist() {
        return [
            ".ru",
            "18+",
            "18yo",
            "aceteminophen",
            "adderall",
            "adidas",
            "adipex",
            "advicer",
            "baccarrat",
            "bdsm",
            "bitch",
            "blackjack",
            "bllogspot",
            "blowjob",
            "bondage",
            "boobs",
            "booker",
            "breast",
            "byob",
            "canabis",
            "carisoprodol",
            "casino",
            "cephalaxin",
            "cialis",
            "citalopram",
            "clomid",
            "cock",
            "coolhu",
            "cougar",
            "cumshot",
            "cyclen",
            "cyclobenzaprine",
            "cymbalta",
            "dating",
            "discount",
            "discreetordering",
            "doxycycline",
            "enlarge",
            "ephedra",
            "erotic",
            "famous",
            "fetish",
            "fioricet",
            "fuck",
            "gambling",
            "gang-bang",
            "gangbang",
            "gloryhole",
            "hair-loss",
            "handjob",
            "holdem",
            "horny",
            "hottest",
            "hqtube",
            "hydrocodone",
            "incest",
            "interacial",
            "jrcreations",
            "lacoste",
            "ladies",
            "ladyboy",
            "lesbian",
            "lesbo",
            "levitra",
            "lexapro",
            "lipitor",
            "loan",
            "lorazepam",
            "lottery",
            "louboutin",
            "lunestra",
            "luxury",
            "macinstruct",
            "marijuana",
            "massage",
            "meridia",
            "mortgage",
            "mp3",
            "mp4",
            "naked",
            "naughty",
            "nike",
            "nsfw",
            "nude",
            // "orgy",
            "ottawavalleyag",
            "ownsthis",
            "oxycodone",
            "oxycontin",
            "p0rn",
            "paxil",
            "paypal",
            "penis",
            "percocet",
            "phentermine",
            "pictures",
            "pills",
            "pokemon",
            "poker",
            "porn",
            "poze",
            "propecia",
            "proxyfree",
            "prozac",
            "punhisment",
            "purchase",
            "pussies",
            "pussy",
            "rebook",
            "rental",
            "ringtones",
            "russian",
            "sex",
            "shemale",
            "shit",
            "slot-machine",
            "slut",
            "spank",
            "submissive",
            "teen",
            "tits",
            "titties",
            "torrent",
            "toys",
            "tramadol",
            "ultram",
            "valium",
            "valtrex",
            "viagra",
            "vicodin",
            "vicoprofen",
            "vioxx",
            "vuitton",
            "wuitton",
            "xanax",
            "xenical",
            "young",
            "zolus",
            "Б",
            "д",
            "ж",
            "и",
            "Ч",
        ];
    }
}
