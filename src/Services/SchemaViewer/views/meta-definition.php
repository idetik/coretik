<?php

$table = app()->get('ux.table');
$def = $args['def'];
$data = [];
$data[] = ['Clé', $def->key()];
$data[] = ['Nom', $def->localName()];
$data[] = ['Valeur par défault', var_export($def->defaultValue(), true)];
$data[] = ['Typage', $def->cast()];
$data[] = ['Protégée', $def->protected() ? 'oui' : 'non'];

$str = '';
foreach ($def->rules() as $rule) {
    $str .= sprintf('<code class="block margin-bottom--2">%s</code>', \Coretik\Core\Utils\Dump::closure($rule));
}
$data[] = ['Règles', $str];

// Key strong
$data = array_map(function ($row) {
    $row[0] = sprintf('<b>%s</b>', $row[0]);
    return $row;
}, $data);


$table->setColumns(['Nom', 'Valeur'])->setData($data)->render();
