<?php

namespace Coretik\Tests\Unit\Models;

use Carbon\Carbon;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Tests\TestCase;

class DateCastsTest extends TestCase
{
    private PostModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = (new \ReflectionClass(PostModel::class))->newInstanceWithoutConstructor();
    }

    private function asDateTime($value): Carbon
    {
        return (new \ReflectionMethod(PostModel::class, 'asDateTime'))->invoke($this->model, $value);
    }

    /**
     * @dataProvider dates
     */
    public function testAsDateTime($value, string $expected): void
    {
        $this->assertSame($expected, $this->asDateTime($value)->format('Y-m-d H:i:s'));
    }

    public static function dates(): array
    {
        return [
            'WordPress date' => ['2026-10-09 10:30:00', '2026-10-09 10:30:00'],
            'stored with microseconds (1.x)' => ['2026-10-09 10:30:00.000000', '2026-10-09 10:30:00'],
            'ACF date' => ['20261009', '2026-10-09 00:00:00'],
            'date' => ['2026-10-09', '2026-10-09 00:00:00'],
            'timestamp' => [1791541800, '2026-10-09 10:30:00'],
            'DateTime' => [new \DateTime('2026-10-09 10:30:00'), '2026-10-09 10:30:00'],
        ];
    }

    public function testFromDateTimeUsesWordPressFormat(): void
    {
        $this->assertSame('2026-10-09 10:30:00', $this->model->fromDateTime(new \DateTime('2026-10-09 10:30:00')));
    }
}
