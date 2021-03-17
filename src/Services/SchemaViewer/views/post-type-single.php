<?php
$args = $builder->args();

$modalArgs = Coretik\App::modals()->factory(function ($data) {
    $array = [];
    $table = Coretik\App::instance()->get('ux.table');
    foreach ($data['args'] as $key => $value) {
        $format = '';

        if (is_object($value)) {
            $value = get_object_vars($value);
        } 

        if (is_array($value)) {
            foreach ($value as $subkey => $subval) {
                if (is_int($subkey)) {
                    $format .= sprintf('<li>%s</li>', $subval);
                } else {
                    $format .= sprintf('<li><b>%s</b>: %s</li>', $subkey, $subval);
                }
            }
        } else {
            $format = $value;
        }
        $array[] = [$key, $format];
    }
    $table->setColumns(['Nom', 'Valeur'])->setData($array);
    $table->render();
}, ['args' => $args]);
?>

<div class="schema-data">
    <a class="schema-data__title" href="#" data-modal="<?= $modalArgs->id() ?>" id="<?= $builder->getName() ?>">
        <h4>
            <span>
                <?php
                !empty($args['menu_icon']) && printf(
                    '<span class="dashicons %s" style="vertical-align:bottom"></span>&nbsp;',
                    $args['menu_icon']
                );
                ?>
                <?= $args['labels']['singular'] ?? $args['labels']['singular_name'] ?? 'Undefined' ?>
            </span>
            <span class="dashicons dashicons-lightbulb"></span>
        </h4>
    </a>
    <ul class="schema-data__tab">
        <li>
            <b>Taxonomies</b>: 
            <?php
            foreach ($builder->taxonomies() as $taxonomy_name) {
                printf('<a href="%s">%s</a>', '#' . $taxonomy_name, Coretik\App::schema($taxonomy_name)->args()->get('labels')['singular']);
            }
            ?>
        </li>
        <?php include 'model-overview.php' ?>
        <li><b>Query</b>: @todo</li>
        <li><b>Handlers</b>: <?= implode(', ', array_map(
            function ($handler) {
                return '<code>' . get_class($handler) . '</code>';
            },
            iterator_to_array($builder->getHandlers())
        )) ?></li>
    </ul>
</div>