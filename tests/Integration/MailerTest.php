<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Email\Mailer;
use Coretik\Services\Email\SendMail;
use Coretik\Services\Email\Viewer\MailCatalog;
use Coretik\Services\Queue\DatabaseStore;
use Coretik\Services\Queue\Queue;
use Coretik\Services\Queue\QueuedJob;
use Coretik\Tests\Integration\Fixtures\FixtureEmail;
use Coretik\Tests\Integration\Fixtures\WelcomeMail;

/**
 * Mailer with wp_mail (MockPHPMailer) and the database queue
 */
class MailerTest extends IntegrationTestCase
{
    private Mailer $mailer;
    private Queue $queue;
    private DatabaseStore $store;

    public function set_up(): void
    {
        parent::set_up();
        reset_phpmailer_instance();

        $this->store = new DatabaseStore();
        $this->queue = new Queue($this->store);
        $this->mailer = (new Mailer(fn () => new FixtureEmail()))
            ->from('noreply@example.org', 'Mon site')
            ->replyTo(['name' => 'Support', 'email' => 'support@example.org'])
            ->useQueue($this->queue);
        $this->mailer->hooks();

        // Job of the queue: the mailer of the test (no "mailer" service in the test app)
        add_filter('coretik/mail/mailer', fn () => $this->mailer);
    }

    private function user(array $data = []): \WP_User
    {
        $id = self::factory()->user->create($data + [
            'user_email' => 'pierre.dupont@example.org',
            'display_name' => 'Pierre Dupont',
            'first_name' => 'Pierre',
        ]);

        return get_userdata($id);
    }

    /**
     * @return object{to: array, cc: array, header: string, subject: string, body: string}[]
     */
    private function sent(): array
    {
        return tests_retrieve_phpmailer_instance()->mock_sent;
    }

    private function lastSent(): object
    {
        return tests_retrieve_phpmailer_instance()->get_sent(count($this->sent()) - 1);
    }

    private function log(\WP_User $user): array
    {
        return get_user_meta($user->ID, 'mail_log');
    }

    private function makeAvailable(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE {$this->store->table()} SET available_at = %d WHERE status = 'pending'", time()));
    }

    public function testSendNow(): void
    {
        $user = $this->user();
        update_user_meta($user->ID, 'second_email', 'marie.dupont@example.org');
        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);

        $this->assertTrue($this->mailer->send(new WelcomeMail($user)));

        $email = $this->lastSent();
        $this->assertSame([['pierre.dupont@example.org', 'Pierre Dupont']], $email->to);
        $this->assertSame([['marie.dupont@example.org', '']], $email->cc);
        $this->assertSame('Bienvenue', $email->subject);
        $this->assertStringContainsString('From: Mon site <noreply@example.org>', $email->header);
        $this->assertStringContainsString('Reply-To: Support <support@example.org>', $email->header);
        // CSS inlined, preheader slot of the project layout (Mail::configure())
        $this->assertMatchesRegularExpression('/class="email-title"[^>]*style="color: #023975/', $email->body);
        $this->assertStringContainsString('Votre espace est prêt', $email->body);
        // Text version (Mailer::phpmailerInit())
        $this->assertStringContainsString('multipart/alternative', $email->header);
        $this->assertStringContainsString('Votre compte (http://example.org/compte/)', $email->body);

        $this->assertSame([['mail' => 'account.welcome', 'sent' => true, 'actor' => $admin]], $this->log($user));
    }

    public function testFailureIsRecorded(): void
    {
        $user = $this->user();
        add_filter('pre_wp_mail', '__return_false');
        $failed = 0;
        add_action('coretik/mail/failed', function () use (&$failed) {
            $failed++;
        });

        $this->assertFalse($this->mailer->send(new WelcomeMail($user)));

        $this->assertSame(1, $failed);
        $this->assertSame([['mail' => 'account.welcome', 'sent' => false, 'actor' => null]], $this->log($user));
    }

    public function testWordPressDefaultSenderIsReplaced(): void
    {
        $this->assertSame('noreply@example.org', apply_filters('wp_mail_from', 'wordpress@example.org'));
        $this->assertSame('Mon site', apply_filters('wp_mail_from_name', 'WordPress'));
        $this->assertSame('other@example.org', apply_filters('wp_mail_from', 'other@example.org'));
    }

    public function testQueuedMailIsSentByTheWorker(): void
    {
        $user = $this->user();
        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);

        $id = $this->mailer->queue(new WelcomeMail($user), 'welcome-batch');

        $job = $this->store->find($id);
        $this->assertSame(SendMail::class, $job->job);
        $this->assertSame('mail', $job->queue);
        $this->assertSame('welcome-batch', $job->group);
        $this->assertSame($admin, $job->payload['actor']);
        $this->assertSame([], $this->sent());
        $this->assertSame([], $this->log($user));

        wp_set_current_user(0);
        $this->assertSame(1, $this->queue->work());

        $this->assertSame('pierre.dupont@example.org', $this->lastSent()->to[0][0]);
        $this->assertSame([['mail' => 'account.welcome', 'sent' => true, 'actor' => $admin]], $this->log($user));
        $this->assertSame(['pending' => 0, 'running' => 0, 'done' => 1, 'failed' => 0], $this->queue->stats('welcome-batch'));
    }

    public function testFailedSendingIsRetriedThenRecorded(): void
    {
        $user = $this->user();
        add_filter('pre_wp_mail', '__return_false');

        $id = $this->mailer->queue(new WelcomeMail($user), 'failing');

        $this->queue->work();
        $this->assertSame(QueuedJob::PENDING, $this->store->find($id)->status);
        $this->assertSame([], $this->log($user), 'A failed attempt retried later is not recorded');

        foreach (range(2, 3) as $attempt) {
            $this->makeAvailable();
            $this->queue->work();
        }

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::FAILED, $job->status);
        $this->assertSame(3, $job->attempts);
        $this->assertSame([['mail' => 'account.welcome', 'sent' => false, 'actor' => null]], $this->log($user));
    }

    public function testDeletedObjectFailsWithoutSending(): void
    {
        $user = $this->user();
        $id = $this->mailer->queue(new WelcomeMail($user));

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID);
        $this->queue->work();

        $this->assertSame(QueuedJob::FAILED, $this->store->find($id)->status);
        $this->assertSame([], $this->sent());
    }

    public function testWithoutQueueTheMailIsSentNow(): void
    {
        $user = $this->user();

        $this->assertNull($this->mailer->useQueue(false)->queue(new WelcomeMail($user)));

        $this->assertCount(1, $this->sent());
        $this->assertSame(0, $this->store->stats()['pending']);
    }

    public function testCoreEmailIsReplaced(): void
    {
        $user = $this->user(['user_email' => 'new.user@example.org']);
        add_filter('wp_new_user_notification_email', fn (array $email, \WP_User $user) => $this->mailer->coreEmail($email, new WelcomeMail($user)), 10, 2);

        wp_new_user_notification($user->ID, null, 'user');

        $email = $this->lastSent();
        $this->assertSame('new.user@example.org', $email->to[0][0]);
        $this->assertSame('Bienvenue', $email->subject);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $email->header);
        $this->assertStringContainsString('<p>Bonjour Pierre,</p>', $email->body);
    }

    public function testViewerTestSendingHasNoSideEffects(): void
    {
        $user = $this->user();
        update_user_meta($user->ID, 'second_email', 'marie.dupont@example.org');
        $catalog = (new MailCatalog($this->mailer))
            ->group('Compte')
            ->add('account.welcome', 'Bienvenue', fn (array $context) => new WelcomeMail($context['user']))
            ->withContext(fn () => ['user' => $user]);

        $preview = $catalog->preview('account.welcome', $catalog->context([]));
        $this->assertSame('Pierre Dupont <pierre.dupont@example.org>', $preview->recipients());
        $this->assertSame('marie.dupont@example.org', $preview->header('Cc'));

        $this->assertTrue($catalog->sendTest('account.welcome', $catalog->context([]), 'admin@example.org'));

        $email = $this->lastSent();
        $this->assertSame([['admin@example.org', '']], $email->to);
        $this->assertSame([], $email->cc);
        $this->assertSame('[Test] Bienvenue', $email->subject);
        $this->assertSame([], $this->log($user));
    }
}
