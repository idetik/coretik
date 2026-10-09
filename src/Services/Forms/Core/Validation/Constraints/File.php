<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Mimes;

class File extends Constraint
{
    protected string $name = 'file';
    protected string $message = 'Le fichier est invalide.';
    protected bool $display_message = true;
    private $maxSize;
    private $types;
    private $required;
    private $formName;

    public function __construct($args, $form)
    {
        $defaults = [
            'max-size' => 10 * 1024 * 1024, // 10MB
            'types' => false,
            'required' => false,
        ];
        $args = wp_parse_args($args, $defaults);
        $this->maxSize  = $args['max-size'];
        $this->types    = \array_map('strtolower', (array)($args['types'] ?: []));
        $this->required = $args['required'];
        $this->form = $form;
        $this->formName = $form?->getFormName() ?: 'coretik-form';
    }

    protected function required()
    {
        if (\is_bool($this->required)) {
            $required = $this->required;
        } elseif (is_callable($this->required)) {
            $required = \call_user_func($this->required, $this->form);
        } else {
            $required = false;
        }

        return $required;
    }

    public function validate($fieldname, $value, $values)
    {
        try {
            if (empty($_FILES[$this->formName])) {
                if ($this->required()) {
                    throw new \RuntimeException('Ce champs est requis.');
                } else {
                    return true;
                }
            }

            // Undefined | Multiple Files | $_FILES Corruption Attack
            // If this request falls under any of them, treat it invalid.
            if (
                !isset($_FILES[$this->formName]['error'][$fieldname]) ||
                is_array($_FILES[$this->formName]['error'][$fieldname])
            ) {
                throw new \RuntimeException('Fichier invalide.');
            }

            // Check $_FILES[$this->formName]['error'] value.
            switch ($_FILES[$this->formName]['error'][$fieldname]) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    if (!$this->required()) {
                        return true;
                    }
                    throw new \RuntimeException('Ce champs est requis.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new \RuntimeException($this->sizeMessage());
                default:
                    throw new \RuntimeException('Unknown errors.');
            }

            if ($this->maxSize && $_FILES[$this->formName]['size'][$fieldname] > $this->maxSize) {
                throw new \RuntimeException($this->sizeMessage());
            }

            $this->checkType(
                (string)($_FILES[$this->formName]['name'][$fieldname] ?? ''),
                $_FILES[$this->formName]['tmp_name'][$fieldname]
            );
        } catch (\RuntimeException $e) {
            $this->message = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * The client file name extension must be an allowed type, and the detected content type must match this extension.
     */
    protected function checkType(string $name, string $path): void
    {
        // Executable extensions are refused anywhere in the name (e.g. "shell.php.jpg")
        if (\preg_match('/\\.(php\\d*|phtml|phar|phps|pht)(\\.|$)/i', $name)) {
            throw new \RuntimeException('Format du fichier invalide.');
        }

        $ext = \strtolower(\pathinfo($name, PATHINFO_EXTENSION));

        if (empty($ext) || !\in_array($ext, $this->types, true)) {
            throw new \RuntimeException('Format du fichier invalide.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!\in_array($mime, Mimes::getType($ext) ?? [], true)) {
            throw new \RuntimeException('Format du fichier invalide.');
        }
    }

    protected function sizeMessage(): string
    {
        return \sprintf('Le fichier doit être inférieur à %s.', \size_format($this->maxSize));
    }
}
