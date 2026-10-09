<?php

namespace Coretik\Tests\Integration;

use Coretik\Tests\Integration\Fixtures\ProductModel;

class PostModelTest extends IntegrationTestCase
{
    private function newProduct(array $props): ProductModel
    {
        $product = app()->schema('product')->model();
        foreach ($props as $key => $value) {
            $product->$key = $value;
        }
        return $product;
    }

    public function testCreateWithMetas(): void
    {
        $product = $this->newProduct(['post_title' => 'Tent', 'post_status' => 'publish', 'price' => '49.90'])->save();

        $this->assertGreaterThan(0, $product->id());
        $this->assertSame('product', get_post_type($product->id()));
        $this->assertSame('49.90', get_post_meta($product->id(), 'price', true));
    }

    public function testFalsyMetasAreKept(): void
    {
        $product = $this->newProduct(['post_title' => 'Tent', 'price' => '0', 'stock' => 0])->save();

        $reloaded = app()->schema('product')->model($product->id(), null, true);
        $this->assertSame('0', $reloaded->meta('price'));
        $this->assertSame('0', $reloaded->meta('stock'));
    }

    public function testSavingUnchangedModelDoesNotFail(): void
    {
        $product = $this->newProduct(['post_title' => 'Tent', 'price' => '10'])->save();

        $product->save();

        $this->assertSame('10', get_post_meta($product->id(), 'price', true));
    }

    public function testProtectedMetaCannotBeWrittenFromOutside(): void
    {
        $id = self::factory()->post->create(['post_type' => 'product']);

        // No product model has been loaded on this request yet: the guard must work anyway
        update_post_meta($id, '_secret', 'hacked');
        add_post_meta($id, '_secret', 'hacked');

        $this->assertSame('', get_post_meta($id, '_secret', true));
    }

    public function testUnprotectedMetaCanBeWrittenFromOutside(): void
    {
        $id = self::factory()->post->create(['post_type' => 'product']);

        update_post_meta($id, 'price', '12');

        $this->assertSame('12', get_post_meta($id, 'price', true));
    }

    public function testOtherObjectTypesAreNotGuarded(): void
    {
        // A user with the same id as a product: its "_secret" meta is not a product meta
        $id = self::factory()->post->create(['post_type' => 'product']);
        $userId = self::factory()->user->create();
        global $wpdb;
        $wpdb->update($wpdb->users, ['ID' => $id], ['ID' => $userId]);
        clean_user_cache($userId);

        update_user_meta($id, '_secret', 'value');

        $this->assertSame('value', get_user_meta($id, '_secret', true));
    }

    public function testEventsAreTriggeredOnce(): void
    {
        $id = $this->newProduct(['post_title' => 'Tent'])->save()->id();
        // Loaded from the schema, as projects do: the handler triggers events on this instance
        $product = app()->schema('product')->model($id);
        $updated = 0;
        $product->on('updated', function () use (&$updated) {
            $updated++;
        });

        $product->post_title = 'Big tent';
        $product->save();
        $this->assertSame(1, $updated);

        // Updated outside of the model (e.g. from the admin): the handler triggers the event
        wp_update_post(['ID' => $product->id(), 'post_title' => 'Huge tent']);
        $this->assertSame(2, $updated);
    }
}
