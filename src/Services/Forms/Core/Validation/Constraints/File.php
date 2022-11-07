<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class File extends Constraint
{
    private $name    = 'file';
    private $message = 'Le fichier est invalide.';
    private $display_message = true;
    private $maxSize;
    private $types;

    public function __construct($args)
    {
        $defaults = [
            'max-size' => 10 * 1024 * 1024, // 10MB
            'types' => false,
            'required' => false,
        ];
        $args = wp_parse_args($args, $defaults);
        $this->maxSize  = $args['max-size'];
        $this->types    = $args['types'];
        $this->required = $args['required'];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function isMessageDisplayed()
    {
        return $this->display_message;
    }

    public function validate($fieldname, $value, $values)
    {
        try {
            $form_name = 'coretik-form'; //TODO this should be the value of form's getFormName()

            // Undefined | Multiple Files | $_FILES Corruption Attack
            // If this request falls under any of them, treat it invalid.
            if (
                !isset($_FILES[$form_name]['error'][$fieldname]) ||
                is_array($_FILES[$form_name]['error'][$fieldname])
            ) {
                throw new \RuntimeException('Invalid parameters.');
            }

            // Check $_FILES[$form_name]['error'] value.
            switch ($_FILES[$form_name]['error'][$fieldname]) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    if (!$this->required) {
                        return true;
                    }
                    throw new \RuntimeException('Le fichier est requis.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new \RuntimeException('Exceeded filesize limit.');
                default:
                    throw new \RuntimeException('Unknown errors.');
            }

            if ($this->maxSize && $_FILES[$form_name]['size'][$fieldname] > $this->maxSize) {
                throw new \RuntimeException('Exceeded filesize limit.');
            }

            $ext = false;
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            foreach ($this->types as $type) {
                if (in_array($finfo->file($_FILES[$form_name]['tmp_name'][$fieldname]), Mimes::getType($type))) {
                    $ext = $type;
                    break;
                }
            }
            if (false === $ext) {
                throw new \RuntimeException('Format du fichier invalide.');
            }
        } catch (\RuntimeException $e) {
            $this->message = $e->getMessage();
            return false;
        }

        return true;
    }
}
