<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;

class AcfProtectFieldsHandler implements HandlerInterface
{
    private $builder;

    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;
        \add_filter('admin_init', [$this, 'prepareFields']);
    }

    public function freeze(): void
    {
        \remove_filter('admin_init', [$this, 'prepareFields']);
    }

    public function prepareFields()
    {
        $model = $this->builder->model();
        $protected_fields = $model->protectedMetaKeys(false);
        foreach ($protected_fields as $field_name) {
            add_filter('acf/load_field/name=' . $field_name, [$this, 'lockField']);
        }
    }

    public function lockField($field)
    {
        $model = $this->builder->model(\get_the_id());
        if (!$model->isProtectedMeta($field['name'])) {
            return $field;
        }
        switch ($field['type']) {
            case 'checkbox':
                $field['disabled'] = \array_keys($field['choices']);
                break;
            case 'post_object':
                $value = $model->meta($field['name']);
                $field['name'] .= '_label';
                $field['type'] = 'text';
                $field['prepend'] = '';
                $field['append'] = '';
                $field['value'] = \get_the_title($value);
                $field['value_id'] = $value;
                $field['disabled'] = 1;
                break;
            case 'wysiwyg':
                $value = $model->meta($field['name']);
                $field['name'] .= '_label';
                $field['type'] = 'textarea';
                $field['prepend'] = '';
                $field['append'] = '';
                $field['value'] = $value;
                $field['disabled'] = 1;
                $field['rows'] = 12;
                break;
            case 'true_false':
            case 'time_picker':
                $field['disabled'] = 1;
                // Warning : True / false acf field don't support attr "disabled"
                \add_action('admin_footer', function () use ($field) {
                    ?>
                    <script type="text/javascript">
                    (function($) {
                        $('[data-key="<?= $field['key'] ?>"] input').prop('disabled', true);
                    })(jQuery); 
                    </script>
                    <?php
                });
                break;
            default:
                $field['disabled'] = 1;
                break;
        }

        return $field;
    }
}
