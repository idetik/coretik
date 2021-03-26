<?php
$modalArgs = Coretik\App::modals()->factory(function () use ($builder) {
    $array = [];
    $table = Coretik\App::instance()->get('ux.table');
    foreach ($builder->getCaps(true) as $key => $value) {
        $format = '';
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
});
?>

<div class="schema-data">
    <a class="schema-data__title" href="#" data-modal="<?= $modalArgs->id() ?>"  id="<?= $builder->getName() ?>">
        <h4>
            <span>
                <?php
                printf(
                    '<span class="dashicons %s" style="vertical-align:bottom"></span>&nbsp;',
                    'dashicons-admin-users'
                );
                ?>
                <?= $builder->getLabel() ?>
            </span>
            <span class="dashicons dashicons-lightbulb"></span>
        </h4>
    </a>
    <ul class="schema-data__tab">
        <li>
            <b>Model</b>: <code><?php echo get_class($builder->model()) ?></code> <a href="#" data-toggle-target="<?= sanitize_title($builder->getLabel()) ?>" data-toggle-classes="hidden"><span class="dashicons dashicons-menu"></span></a>
            <ul class="hidden schema-data__tab" id="<?= sanitize_title($builder->getLabel()) ?>">
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