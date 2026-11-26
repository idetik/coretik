<?php

namespace Coretik\Testing;

use Coretik\Core\Builders\BuilderModelable;
use Coretik\Core\Builders\UserType;
use Coretik\Core\Models\Model;
use Coretik\Core\Query\QueryCache;

/**
 * Base class of the integration tests of a coretik project (WordPress test suite, see Bootstrap).
 *
 * Each test runs in a database transaction rolled back afterwards: the coretik static caches (models, queries) and the
 * ACF stores are reset too, the roles of the schema are saved again. Emails are caught by the MockPHPMailer of the
 * WordPress test suite (never sent).
 *
 * Only loaded by the tests: it extends \WP_UnitTestCase, which exists in the WordPress test suite only.
 */
abstract class IntegrationTestCase extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        $this->newRequest();
        \reset_phpmailer_instance();

        // ACF form data of a previous test (post id set by acf_save_post()…): read first by acfe_get_post_id(), before
        // $_REQUEST['post'], it would point the back-office screens to a post of that test
        if (\function_exists('acf_get_store')) {
            \acf_get_store('form')->reset();
        }

        $this->persistRoles();
    }

    /**
     * Forgets the models and queries kept in memory, as a new HTTP request would: needed after a change made outside
     * of the models (wp_update_post(), update_post_meta()…)
     */
    protected function newRequest(): void
    {
        // Emptied in place: the factory of each builder keeps its models container
        foreach ((new \ReflectionProperty(BuilderModelable::class, 'models'))->getValue() ?? [] as $models) {
            if ($models instanceof \ArrayAccess && $models instanceof \Traversable) {
                foreach (\array_keys(\iterator_to_array($models)) as $id) {
                    unset($models[$id]);
                }
            }
        }
        (new \ReflectionProperty(QueryCache::class, 'instance'))->setValue(null, null);

        // ACF field values loaded during the request (update_post_meta() does not clear them)
        if (\function_exists('acf_get_store')) {
            \acf_get_store('values')->reset();
        }
    }

    /**
     * The model loaded again from the database, after newRequest()
     *
     * @template T of Model
     * @param T $model
     * @return T
     */
    protected function fresh(Model $model): Model
    {
        $this->newRequest();
        /** @var T */
        return \coretik()->schema()->modelable((string)$model->name())->model($model->id());
    }

    /**
     * Roles of the user types of the schema, with their capabilities: coretik saves them on back-office requests only,
     * a fresh test database has none of them. Saved before each test: a role changed in memory by a test (add_cap(),
     * remove_role()…) is not restored by the database rollback.
     */
    protected function persistRoles(): void
    {
        foreach (\coretik()->schema()->type('user')->all() as $builder) {
            if ($builder instanceof UserType) {
                $builder->persist();
            }
        }
    }

    /**
     * New user of this role, logged in
     *
     * @param array $args wp_insert_user() arguments (user_email…)
     */
    protected function loginAs(string $role, array $args = []): \WP_User
    {
        $user = self::factory()->user->create_and_get(['role' => $role] + $args);
        \wp_set_current_user($user->ID);
        return $user;
    }

    /**
     * New administrator, logged in
     */
    protected function admin(array $args = []): \WP_User
    {
        return $this->loginAs('administrator', $args);
    }

    /**
     * New subscriber (logged in user without any right), logged in
     */
    protected function subscriber(array $args = []): \WP_User
    {
        return $this->loginAs('subscriber', $args);
    }

    protected function logout(): void
    {
        \wp_set_current_user(0);
    }

    /**
     * Runs the background jobs due (coretik queue service: emails sent by a job…), as WP-Cron does
     *
     * @return int jobs run
     */
    protected function workQueue(): int
    {
        return \coretik()->queue()->work();
    }

    /**
     * Emails caught by the MockPHPMailer since the beginning of the test
     *
     * @return array<array{to: array, cc: array, bcc: array, header: string, subject: string, body: string}>
     */
    protected function sentEmails(): array
    {
        return \tests_retrieve_phpmailer_instance()->mock_sent;
    }

    /**
     * Last email sent, fails the test when none
     *
     * @return array{to: array, cc: array, bcc: array, header: string, subject: string, body: string}
     */
    protected function lastEmail(): array
    {
        $sent = $this->sentEmails();
        $this->assertNotEmpty($sent, 'No email sent');
        return \end($sent);
    }
}
