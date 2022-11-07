<div class="wrap">
    <h2>Schéma de l'application <?= get_bloginfo('name') ?></h2>

    <div class="boxes">
        <div class="boxes__box">
            <div class="schema-box">
                <h3>Post types</h3>
                <?php
                if (app()->schema()->has('post')) {
                    foreach (app()->schema()->type('post')->all() as $builder) {
                        include 'post-type-single.php';
                    }
                }
                ?>
            </div>
            <div class="schema-box">
                <h3>Taxonomies</h3>
                <?php
                if (app()->schema()->has('taxonomy')) {
                    foreach (app()->schema()->type('taxonomy')->all() as $builder) {
                        include 'taxonomy-single.php';
                    }
                }
                ?>
            </div>
        </div>
        <div class="boxes__box">
            <div class="schema-box">
                <h3>User types</h3>
                <?php
                if (app()->schema()->has('user')) {
                    foreach (
                        app()->schema()->type('user')->filter(function ($builder) {
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
                if (app()->schema()->has('comment')) {
                    foreach (
                        app()->schema()->type('comment')->all() as $builder
                    ) {
                        include 'comment-single.php';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <?php

    ?>
    <!-- <pre> -->
        <?php //var_dump(get_post_types()); ?>
        <?php //var_dump(get_post_stati()); ?>
        <?php //var_dump(app()->schema()->toArray()); ?>
    <!-- </pre> -->
</div>
