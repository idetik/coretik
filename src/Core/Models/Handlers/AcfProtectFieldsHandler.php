<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Utils\Classes;

class AcfProtectFieldsHandler implements HandlerInterface
{
    private $builder;
    private static $stylesLoaded = false;

    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;
        \add_filter('admin_init', [$this, 'prepareFields']);
        \add_filter('acf/update_value', [$this, 'maybeRestoreOldValue'], 5, 3);
    }

    public function freeze(): void
    {
        \remove_filter('admin_init', [$this, 'prepareFields']);
        \remove_filter('acf/update_value', [$this, 'maybeRestoreOldValue'], 5);
    }

    public function prepareFields()
    {
        if (!function_exists('acfe_get_post_id') || empty(\acfe_get_post_id())) {
            return;
        }

        $model_id = (int)\acf_decode_post_id(\acfe_get_post_id())['id'];
        if (!$this->builder->concern($model_id)) {
            return;
        }

        $model = $this->builder->model($model_id);
        $protected_fields = $model->protectedMetaKeys(false);

        foreach ($protected_fields as $field_name) {
            \add_filter('acf/load_field/name=' . $field_name, [$this, 'lockField']);
        }
    }

    public function maybeRestoreOldValue($value, $post_id, $field)
    {
        if ($field['type'] !== 'repeater') {
            return $value;
        }

        $model_id = (int)$post_id;
        if (!$this->builder->concern($model_id)) {
            return;
        }

        $model = $this->builder->model((int)$model_id);

        if (\in_array('Coretik\Core\Models\Traits\Metable', Classes::classUsesDeep($model))) {
            $key = $model->getLocalKeyFromMetaKey($field['name']);
            if ($model->isProtectedMeta($key)) {
                return $model->$key;
            }
        }

        return $value;
    }

    public function lockField($field, bool $force = false)
    {
        $model_id = (int)\acf_decode_post_id(\acfe_get_post_id())['id'];
        $model = $this->builder->model($model_id);
        if (!$force && !$model->isProtectedMeta($field['name'])) {
            return $field;
        }

        switch ($field['type']) {
            case 'checkbox':
            case 'radio':
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
            case 'button_group':
            case 'taxonomy':
            case 'acfe_date_range_picker':
                $field['disabled'] = 1;
                // Warning : True / false acf field don't support attr "disabled"
                \add_action('admin_footer', function () use ($field) {
                    ?>
                    <script type="text/javascript">
                    (function($) {
                        $('[data-key="<?= $field['key'] ?>"] input').addClass('acf-disabled').prop('disabled', true);
                        $('[data-key="<?= $field['key'] ?>"] select').prop('disabled', true);
                    })(jQuery); 
                    </script>
                    <?php
                });
                break;
            case 'image':
                $field['disabled'] = 1;
                $field['readonly'] = 1;
                $field['wrapper']['class'] .= ' acf-field-image-readonly';
                static::addStyles();
                break;
            case 'gallery':
                $field['disabled'] = 1;
                $field['readonly'] = 1;
                $field['wrapper']['class'] .= ' acf-field-gallery-readonly';
                static::addStyles();
                break;
            case 'repeater':
                $field['disabled'] = 1;
                $field['wrapper']['class'] .= ' acf-field-repeater-readonly';
                $field['sub_fields'] = array_map(fn ($subfield) => $this->lockField($subfield, true), $field['sub_fields']);
                \add_action('admin_footer', function () use ($field) {
                    ?>
                    <script type="text/javascript">
                    (function($) {
                        $('[data-key="<?= $field['key'] ?>"] .acf-repeater-add-row').remove();
                        $('[data-key="<?= $field['key'] ?>"] .acfe-repeater-stylised-button').remove();
                        $('[data-key="<?= $field['key'] ?>"] .acf-row').each(function(index, element) {
                            if ($(element).hasClass('acf-clone')) {
                                return;
                            }
                            $(element).find('.acf-input input').attr('readonly', true);
                            $(element).find('a[data-event="remove-row"]').remove();
                            $(element).find('a[data-event="add-row"]').remove();
                        });
                    })(jQuery); 
                    </script>
                    <?php
                });
                break;
            default:
                $field['disabled'] = 1;
                break;
        }

        \add_filter('acf/prepare_field', function ($prep_field) use ($field) {
            if ($prep_field['key'] !== $field['key']) {
                return $prep_field;
            }

            $prep_field['class'] = 'acf-disabled';
            return $prep_field;
        });

        return $field;
    }

    protected static function addStyles()
    {
        if (static::$stylesLoaded) {
            return;
        }

        static::$stylesLoaded = true;

        add_action('admin_footer', function () {
            ?>
            <style>
                /* Gallery */
                .acf-field-gallery-readonly .actions {
                    display: none !important;
                }
                .acf-field-gallery-readonly .acf-gallery-main .acf-gallery-toolbar {
                    display: none !important;
                }

                /* Repeater */
                .acf-field-repeater-readonly .acfe-repeater-stylised-button {
                    display: none !important;
                }

                /* Image */
                .acf-field-image-readonly {
                    cursor: not-allowed;
                }
                .acf-field-image-readonly .acf-actions {
                    display: none !important;
                }
                .acf-field-image-readonly .hide-if-value {
                    display: none !important;
                }
            </style>
            <?php
        });
    }
}
