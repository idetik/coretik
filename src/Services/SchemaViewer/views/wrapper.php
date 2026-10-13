<div class="wrap">
    <h2>Schéma de l'application <?= get_bloginfo('name') ?></h2>

    <div class="boxes">
        <div class="boxes__box">
            <div class="schema-box">
                <h3>Post types</h3>
                <?php
                if (coretik()->schema()->has('post')) {
                    foreach (coretik()->schema()->type('post')->all() as $builder) {
                        include 'post-type-single.php';
                    }
                }
                ?>
            </div>
            <div class="schema-box">
                <h3>Taxonomies</h3>
                <?php
                if (coretik()->schema()->has('taxonomy')) {
                    foreach (coretik()->schema()->type('taxonomy')->all() as $builder) {
                        include 'taxonomy-single.php';
                    }
                }
                foreach (\Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler::shadowTaxonomies() as $taxonomy => $shadow) {
                    include 'shadow-taxonomy-single.php';
                }
                ?>
            </div>
        </div>
        <div class="boxes__box">
            <div class="schema-box">
                <h3>User types</h3>
                <?php
                if (coretik()->schema()->has('user')) {
                    foreach (
                        coretik()->schema()->type('user')->filter(function ($builder) {
                            return $builder->getName() !== 'users';
                        })->all() as $builder
                    ) {
                        include 'user-type-single.php';
                    }
                }
                ?>
            </div>
            <div class="schema-box">
                <h3>Comments</h3>
                <?php
                if (coretik()->schema()->has('comment')) {
                    foreach (
                        coretik()->schema()->type('comment')->all() as $builder
                    ) {
                        include 'comment-single.php';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <div class="schema-box schema-relations">
        <h3>Relations</h3>
        <?php
        try {
            $relationsMap = new \Coretik\Services\SchemaViewer\Relations\RelationsMap(coretik()->schema());
            $relationsDiagram = !empty($relationsMap->relations()) ? $relationsMap->mermaid() : '';
        } catch (\Throwable $e) {
            $relationsDiagram = '';
            printf('<p>%s</p>', \esc_html('Relations could not be discovered: ' . $e->getMessage()));
        }
        ?>
        <?php if (!empty($relationsDiagram)) : ?>
            <pre class="mermaid"><?= \esc_html($relationsDiagram) ?></pre>
            <details>
                <summary>Mermaid</summary>
                <textarea readonly rows="10" class="large-text code"><?= \esc_textarea($relationsDiagram) ?></textarea>
            </details>
            <script src="https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js"></script>
            <script>mermaid.initialize({startOnLoad: true, securityLevel: 'strict'});</script>
        <?php else : ?>
            <p>No relation found.</p>
        <?php endif; ?>
    </div>

    <!-- <pre> -->
        <?php //var_dump(get_post_types()); ?>
        <?php //var_dump(get_post_stati()); ?>
        <?php //var_dump(coretik()->schema()->toArray()); ?>
    <!-- </pre> -->
</div>
