<?php

namespace Coretik\Tests\Integration\Fixtures\Forms;

use Coretik\Services\Forms\JsonForm;

/**
 * JSON form whose template is the json-form-open / json-form-close default parts around one field.
 */
class ContactJsonForm extends JsonForm
{
    const ID = 'contact-json';

    public function getRules(): array
    {
        return ['message' => ['name' => 'Message', 'constraints' => ['required' => true]]];
    }

    protected function isValidContext(): bool
    {
        return true;
    }

    protected function run(): void
    {
        \update_option('contact_messages', \array_merge((array)\get_option('contact_messages', []), [$this->getValues()['message']]));
    }

    public function view($data = [], bool $return = false)
    {
        $this->template = 'parts/json-form-open';
        $open = parent::view($data, true);

        $html = $open . '<div data-field="message"><textarea name="' . \esc_attr($this->fieldName('message')) . '"></textarea></div>'
            . $this->viewPart('parts/json-form-close', [], true);

        if ($return) {
            return $html;
        }
        echo $html;
    }
}
