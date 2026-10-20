<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Collection;
use Coretik\Core\Models\Exceptions\UndefinedMetaKeyException;
use Coretik\Tests\Integration\Fixtures\ProductModel;

class QuietMetaTest extends IntegrationTestCase
{
    private function product(array $metas = []): ProductModel
    {
        $product = coretik()->schema('product')->model();
        $product->post_title = 'Tent';
        foreach ($metas as $key => $value) {
            $product->$key = $value;
        }
        $product->save();

        // Loaded from the schema, as projects do
        return coretik()->schema('product')->model($product->id(), null, true);
    }

    public function testUpdateWritesWithoutSavingTheModel(): void
    {
        $product = $this->product(['price' => '10']);
        $modified = get_post_field('post_modified_gmt', $product->id());

        $events = [];
        foreach (['saving', 'updating', 'updated', 'saved'] as $event) {
            $product->on($event, function () use (&$events, $event) {
                $events[] = $event;
            });
        }
        $savePost = 0;
        $countSavePost = function () use (&$savePost) {
            $savePost++;
        };
        add_action('save_post', $countSavePost);

        $product->updateMetaQuietly('price', '12');

        remove_action('save_post', $countSavePost);
        $this->assertSame('12', get_post_meta($product->id(), 'price', true));
        $this->assertSame('12', $product->meta('price'));
        $this->assertSame([], $events);
        $this->assertSame(0, $savePost);
        $this->assertSame($modified, get_post_field('post_modified_gmt', $product->id()));
    }

    public function testPendingValueIsDropped(): void
    {
        $product = $this->product(['price' => '10']);

        $product->price = '99';
        $product->updateMetaQuietly('price', '12');
        $this->assertSame('12', $product->price);

        // A later save() does not write the dropped value back
        $product->post_title = 'Big tent';
        $product->save();
        $this->assertSame('12', get_post_meta($product->id(), 'price', true));
    }

    public function testSeveralMetasByLocalOrMetaKey(): void
    {
        $product = $this->product();

        $product->updateMetasQuietly(['price' => '15', 'stock' => 3]);

        $this->assertSame('15', get_post_meta($product->id(), 'price', true));
        $this->assertSame('3', get_post_meta($product->id(), 'stock', true));
    }

    public function testCollectionIsStoredAsArray(): void
    {
        $product = $this->product();

        $product->updateMetaQuietly('stock', new Collection(['a' => 1, 'b' => 2]));

        $this->assertSame(['a' => 1, 'b' => 2], get_post_meta($product->id(), 'stock', true));
    }

    public function testKeysAreCheckedBeforeWriting(): void
    {
        $product = $this->product(['price' => '10']);

        try {
            $product->updateMetasQuietly(['price' => '20', 'undeclared' => 'x']);
            $this->fail('An undeclared meta must be rejected');
        } catch (UndefinedMetaKeyException $e) {
            $this->assertSame('10', get_post_meta($product->id(), 'price', true));
        }
    }

    public function testProtectedMetaIsRejected(): void
    {
        $product = $this->product();

        $this->expectException(\LogicException::class);
        $product->updateMetaQuietly('secret', 'value');
    }

    public function testUnsavedModelIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        coretik()->schema('product')->model()->updateMetaQuietly('price', '10');
    }

    public function testDelete(): void
    {
        $product = $this->product(['price' => '10']);

        $product->deleteMetaQuietly('price');
        $this->assertFalse(metadata_exists('post', $product->id(), 'price'));

        // Nothing to delete: no failure
        $product->deleteMetaQuietly('price');
        $this->assertFalse(metadata_exists('post', $product->id(), 'price'));
    }
}
