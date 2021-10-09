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
                <?= $args['labels']['singular'] ?>
            </span>
            <span class="dashicons dashicons-lightbulb"></span>
        </h4>
    </a>
    <ul class="schema-data__tab">
        <li>
            <b>Object types</b>: 
            <?php
            foreach ($builder->getObjectTypes() as $post_type_name) {
                printf('<a href="%s">%s</a>', '#' . $post_type_name, Coretik\App::schema($post_type_name)->args()->get('labels')['singular']);
            }
            ?>
        </li>
        <li>
            <b>Model</b>: <code><?php echo get_class($builder->model()) ?></code> <a href="#" data-toggle-target="<?= sanitize_title($args['labels']['singular']) ?>" data-toggle-classes="hidden"><span class="dashicons dashicons-menu"></span></a>
            <ul class="hidden schema-data__tab" id="<?= sanitize_title($args['labels']['singular']) ?>">
                <li><b>Metas</b>: 
                    <?php
                    $model = $builder->model();
                    $table = Coretik\App::instance()->get('ux.table');
                    $table->setColumns(['Nom', 'Clé (meta_key)', ''])->setData(array_map(function ($def) {

                        $modal = Coretik\App::modals()->factory(function ($args) {
                            include 'meta-definition.php';
                        }, ['def' => $def]);

                        return [
                            $def->localName(),
                            $def->key(),
                            '<div class="flex flex--justify-flex-end"><a href="#" data-modal="' . $modal->id() . '"><span class="dashicons dashicons-info"></span></a></div>',
                            'filters' => [
                                'protected' => $def->protected() ? 'true' : 'false',
                            ],
                        ];
                    }, $model->allMetas(), []))->setFilters([
                        'protected' => [
                            'true' => 'Protégée'
                        ]
                    ])->render();
                    ?>
                </li>
            </ul>
        </li>
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