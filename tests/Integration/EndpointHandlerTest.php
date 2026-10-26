<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Models\Handlers\EndpointHandler;
use Coretik\Core\Models\Interfaces\ModelInterface;

/**
 * "event" and "course" post types (tests/Integration/bootstrap.php) share the endpoint "inscription",
 * "event" also has "billet", which accepts a value
 */
class EndpointHandlerTest extends IntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        $this->permalinks('/%postname%/');
    }

    /**
     * set_permalink_structure() resets the rewrite endpoints (WP_Rewrite::init()), registered once on init, and the
     * post types got no permastruct on init (no permalink structure in the test database then)
     */
    private function permalinks(string $structure): void
    {
        global $wp_rewrite;

        $this->set_permalink_structure($structure);
        foreach (['event', 'course'] as $postType) {
            get_post_type_object($postType)->add_rewrite_rules();
            foreach (coretik()->schema($postType)->getHandlers() as $handler) {
                if ($handler instanceof EndpointHandler) {
                    $handler->registerEndpoint();
                }
            }
        }
        $wp_rewrite->flush_rules();
    }

    private function handler(string $postType, string $endpoint): EndpointHandler
    {
        foreach (coretik()->schema($postType)->getHandlers() as $handler) {
            if ($handler instanceof EndpointHandler && $endpoint === $handler->endpoint()) {
                return $handler;
            }
        }

        $this->fail(sprintf('No endpoint "%s" on %s', $endpoint, $postType));
    }

    private function post(string $postType = 'event'): int
    {
        return self::factory()->post->create(['post_type' => $postType, 'post_status' => 'publish', 'post_title' => $postType . ' ' . wp_generate_password(6, false)]);
    }

    public function testRulesAreAddedToThePostTypes(): void
    {
        $rules = get_option('rewrite_rules');

        $this->assertArrayHasKey('events/([^/]+)/inscription(/(.*))?/?$', $rules);
        $this->assertArrayHasKey('courses/([^/]+)/inscription(/(.*))?/?$', $rules);
        $this->assertArrayHasKey('events/([^/]+)/billet(/(.*))?/?$', $rules);
    }

    public function testRequestedEndpoint(): void
    {
        $id = $this->post();
        $handler = $this->handler('event', 'inscription');
        $url = $handler->url($id);

        $this->assertSame(trailingslashit(get_permalink($id)) . 'inscription/', $url);

        $this->go_to($url);
        $this->assertTrue($handler->isRequested());
        $this->assertSame('', $handler->value());
        $this->assertSame(1, get_query_var('inscription'));
        $this->assertFalse($this->handler('course', 'inscription')->isRequested());
        $this->assertFalse($this->handler('event', 'billet')->isRequested());

        $this->go_to(get_permalink($id));
        $this->assertFalse($handler->isRequested());
        $this->assertNull($handler->value());
    }

    public function testCallableReceivesTheModelAndTheValue(): void
    {
        $id = $this->post();
        $handler = $this->handler('event', 'billet');
        $calls = [];
        $handler->setHandler(function (ModelInterface $model, string $value) use (&$calls) {
            $calls[] = [$model->id(), $value];
        });

        $this->go_to($handler->url($id, 'A-12'));
        $handler->handleRequest();

        $this->assertSame('A-12', $handler->value());
        $this->assertSame([[$id, 'A-12']], $calls);
        $this->assertFalse(is_404());
    }

    public function testNoCallableIsNeeded(): void
    {
        $handler = $this->handler('course', 'inscription');
        $this->go_to($handler->url($this->post('course')));

        $handler->handleRequest();

        $this->assertTrue($handler->isRequested());
        $this->assertFalse(is_404());
    }

    public function testOtherPostTypesOfTheEndpointAreNotA404(): void
    {
        $this->go_to($this->handler('course', 'inscription')->url($this->post('course')));

        $this->handler('event', 'inscription')->handleRequest();

        $this->assertFalse(is_404());
    }

    /**
     * Posts share the EP_PERMALINK mask: /{post}/inscription/ must not duplicate the post
     */
    public function testElsewhereIsA404(): void
    {
        $post = self::factory()->post->create(['post_status' => 'publish', 'post_title' => 'Article']);
        $this->go_to(trailingslashit(get_permalink($post)) . 'inscription/');
        $this->assertSame(1, get_query_var('inscription'));

        $this->handler('event', 'inscription')->handleRequest();

        $this->assertTrue(is_404());
    }

    public function testUnexpectedValueIsA404(): void
    {
        $handler = $this->handler('event', 'inscription');
        $this->go_to($handler->url($this->post(), 'foo'));

        $this->assertSame('foo', $handler->value());
        $this->assertFalse($handler->isRequested());

        $handler->handleRequest();
        $this->assertTrue(is_404());
    }

    public function testPlainPermalinks(): void
    {
        $this->permalinks('');
        $id = $this->post();
        $handler = $this->handler('event', 'inscription');
        $url = $handler->url($id);

        $this->assertStringContainsString('inscription=1', $url);

        $this->go_to($url);
        $this->assertTrue($handler->isRequested());
    }

    public function testTemplateRobotsAndAdminBar(): void
    {
        $handler = $this->handler('event', 'inscription');
        $course = $this->handler('course', 'inscription');

        $this->go_to($handler->url($this->post()));

        // No such template in the test theme: the single template is kept
        $this->assertSame('single.php', $handler->templateInclude('single.php'));
        $this->assertTrue($handler->robots([])['noindex'] ?? false);
        $this->assertFalse($handler->showAdminBar(true));
        // Options of the "course" handler: none
        $this->assertSame([], $course->robots([]));
        $this->assertTrue($course->showAdminBar(true));

        $this->go_to(get_permalink($this->post()));
        $this->assertSame([], $handler->robots([]));
        $this->assertTrue($handler->showAdminBar(true));
    }

    public function testRulesWithoutTheEndpointAreFlushedOnce(): void
    {
        $rules = get_option('rewrite_rules');
        update_option('rewrite_rules', array_filter($rules, fn ($query) => !str_contains($query, '&billet=')));
        (new \ReflectionProperty(EndpointHandler::class, 'flushChecked'))->setValue(null, false);

        $this->handler('event', 'inscription')->flushIfMissing();

        $this->assertArrayHasKey('events/([^/]+)/billet(/(.*))?/?$', get_option('rewrite_rules'));
    }
}
