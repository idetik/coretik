<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Core\JsonResponse;
use Coretik\Services\Forms\Core\Validation\Constraints\Required;
use Coretik\Services\Forms\Enums\RgpdDataCategory;
use Coretik\Tests\TestCase;

class JsonResponseTest extends TestCase
{
    public function testErrorsListTheMessagesOfEachField(): void
    {
        $this->assertSame(
            [['field' => 'email', 'messages' => ['Ce champs est requis.'], 'step' => 2]],
            JsonResponse::errors(['email' => [new Required()]], 2)
        );
        $this->assertSame([['field' => 'email', 'messages' => ['Ce champs est requis.']]], JsonResponse::errors(['email' => [new Required()]]));
    }

    public function testValuesHaveALabelAndAPrivateFlag(): void
    {
        $values = JsonResponse::values(
            ['email' => 'jane@example.org', 'code' => '42'],
            fn ($field) => 'email' === $field ? 'Email' : '',
            fn ($field) => 'code' === $field
        );

        $this->assertSame([
            ['field' => 'email', 'label' => 'Email', 'private' => false, 'value' => 'jane@example.org'],
            ['field' => 'code', 'label' => 'code', 'private' => true, 'value' => '42'],
        ], $values);
    }

    public function testTheContentOfAFileIsNotSentBack(): void
    {
        $file = ['type' => 'file', 'name' => 'cv.pdf', 'mime' => 'application/pdf', 'content' => 'base64'];

        $this->assertSame(['type' => 'file', 'name' => 'cv.pdf', 'mime' => 'application/pdf'], JsonResponse::publicValue($file));
        $this->assertSame(['a', 'b'], JsonResponse::publicValue(['a', 'b']));
    }

    public function testPrivateRule(): void
    {
        $rules = ['email' => ['rgpd' => ['private' => true]], 'name' => []];

        $this->assertTrue(JsonResponse::isPrivate($rules, 'email'));
        $this->assertFalse(JsonResponse::isPrivate($rules, 'name'));
        $this->assertFalse(JsonResponse::isPrivate($rules, 'unknown'));
    }

    public function testRgpdCategoriesHaveTranslatableLabels(): void
    {
        Functions\stubTranslationFunctions();

        $this->assertSame('Données de contact', RgpdDataCategory::CONTACT_DATA->label());
        $this->assertCount(\count(RgpdDataCategory::cases()), RgpdDataCategory::toArray());
        $this->assertSame('Données de santé', RgpdDataCategory::toArray()['health_data']);
    }
}
