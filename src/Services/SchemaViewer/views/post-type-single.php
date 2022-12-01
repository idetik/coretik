<?php
$args = $builder->args();

$modalArgs = app()->modals()->factory(function ($data) {
    $array = [];
    $table = app()->get('ux.table');
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
                    $format .= sprintf('<li><b>%s</b>: %s</li>', $subkey, is_array($subval) ? implode(', ', $subval) : $subval);
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
                printf('<a href="%s">%s</a>&nbsp;', '#' . $taxonomy_name, app()->schema($taxonomy_name)->args()->get('labels')['singular']);
            }
            ?>
        </li>
        <?php include 'model-overview.php' ?>
        <?php $query = apply_filters('coretik/services/schemaViewer/query', $builder->query(), $builder, $args); ?>
        <li><b>Query</b>: <code><?php echo get_class($query) ?></code></li>
        <li><b>Handlers</b>: <?= implode(', ', array_map(
            function ($handler) {
                return '<code>' . get_class($handler) . '</code>';
            },
            iterator_to_array($builder->getHandlers())
        )) ?></li>
    </ul>
</div>