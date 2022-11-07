<?php

$models = apply_filters('coretik/services/schemaViewer/models', [$builder->model()], $builder, $args);

foreach ($models as $i => $model) :
    $id = sprintf('%s-%s', sanitize_title($args['labels']['singular'] ?? $args['labels']['singular_name'] ?? 'Undefined'), $i);
    ?>
    <li>
        <b>Model</b>: <code><?php echo get_class($model) ?></code> <a href="#" data-toggle-target="<?= $id ?>" data-toggle-classes="hidden"><span class="dashicons dashicons-menu"></span></a>
        <ul class="hidden schema-data__tab" id="<?= $id ?>">
            <li><b>Metas</b>: 
                <?php
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
            <!-- <li><b>Queue</b>: @todo</li> -->
        </ul>
    </li>
    <?php
endforeach;