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
    <!-- <li><b>Queue</b>: @todo</li> -->
</ul>
<!-- <form action="<?//= add_query_arg('load-model', $builder->getName()) ?>">
    <label for="field-id-model_id">Model id: </label>
    <input type="number" name="model_id" id="field-id-model_id"/>
</form> -->
