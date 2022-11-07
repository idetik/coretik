<div class="schema-data">
    <a class="schema-data__title" href="#">
        <h4>
            <span>
                <?php
                printf(
                    '<span class="dashicons %s" style="vertical-align:bottom"></span>&nbsp;',
                    'dashicons-admin-comments'
                );
                ?>
                Défault
            </span>
            <span class="dashicons dashicons-lightbulb"></span>
        </h4>
    </a>
    <ul class="schema-data__tab">
        <li>
            <b>Model</b>: <code><?php echo get_class($builder->model()) ?></code> <a href="#" data-toggle-target="<?= sanitize_title($builder->getName()) ?>" data-toggle-classes="hidden"><span class="dashicons dashicons-menu"></span></a>
            <ul class="hidden schema-data__tab" id="<?= sanitize_title($builder->getName()) ?>">
                <li><b>Metas</b>: 
                    <?php
                    $model = $builder->model();
                    $table = app()->instance()->get('ux.table');
                    $table->setColumns(['Nom', 'Clé (meta_key)', ''])->setData(array_map(function ($def) {

                        $modal = app()->modals()->factory(function ($args) {
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