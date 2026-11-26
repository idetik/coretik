<?php

/**
 * Opens a JSON form (Coretik\Services\Forms\JsonForm, Tunnel\TunnelForm) driven by the forms scripts:
 * <form> tag, hidden fields (form id, nonce, honeypot, persistent data) and the containers filled by the scripts.
 * Close it with parts/json-form-close. Override: templates/forms/parts/json-form-open.php in the theme.
 *
 * @var \Coretik\Services\Forms\Form $form
 * @var array $form_data Data of the form view (uniqid, ajax_endpoint, ajax_action, successMessage, persistent_data...)
 * @var string|null $class CSS classes of the <form>
 * @var string|null $action Form action, the current URL by default
 * @var string|null $enctype
 * @var string[]|null $data_atts Extra attributes, printed as is: escape them
 */

$form_data = $form_data ?? [];
$data_atts = array_merge($form_data['data_atts'] ?? [], $data_atts ?? []);
$data_atts[] = $form instanceof \Coretik\Services\Forms\Tunnel\TunnelFormInterface ? 'data-form-tunnel' : 'data-form';

?>
<form
    id="<?= esc_attr($form_data['uniqid'] ?? $form->id()) ?>"
    data-form-id="<?= esc_attr($form->id()) ?>"
    method="post"
    <?= !empty($action) ? 'action="' . esc_url($action) . '"' : '' ?>
    <?= !empty($class) ? 'class="' . esc_attr($class) . '"' : '' ?>
    <?= !empty($enctype) ? 'enctype="' . esc_attr($enctype) . '"' : '' ?>
    data-ajax-endpoint="<?= esc_url($form_data['ajax_endpoint'] ?? admin_url('admin-ajax.php')) ?>"
    <?= !empty($form_data['ajax_action']) ? 'data-ajax-action="' . esc_attr($form_data['ajax_action']) . '"' : '' ?>
    <?= implode(' ', $data_atts) ?>
    novalidate
>
    <input type="hidden" name="<?= esc_attr($form->fieldName('form_id')) ?>" value="<?= esc_attr($form->id()) ?>">
    <?php
    $form->nonceField();
    $form->spamField();

    foreach ($form_data['persistent_data'] ?? [] as $key => $value) :
        printf('<input type="hidden" name="persistent-data[%s]" value="%s">', esc_attr($key), esc_attr($value));
    endforeach;
    ?>
    <div class="form-error form-error--global" data-form-errors role="alert"></div>
    <div class="form-notice" data-form-notices role="status"></div>
    <div
        class="form-success-container"
        data-form-success
        data-success-message="<?= esc_attr($form_data['successMessage'] ?? __('Votre demande a bien été envoyée.', 'coretik')) ?>"
        aria-live="polite"
    ></div>
