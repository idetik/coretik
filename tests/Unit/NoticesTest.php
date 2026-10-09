<?php

namespace Coretik\Tests\Unit;

use Brain\Monkey\Functions;
use Coretik\Services\Notices\NoticeError;
use Coretik\Services\Notices\NoticeInfo;
use Coretik\Services\Notices\NoticeSuccess;
use Coretik\Services\Notices\NoticeWarning;
use Coretik\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NoticesTest extends TestCase
{
    public static function notices(): array
    {
        return [[NoticeError::class], [NoticeInfo::class], [NoticeSuccess::class], [NoticeWarning::class]];
    }

    #[DataProvider('notices')]
    public function testMessageIsFilteredWithKses(string $class): void
    {
        Functions\expect('wp_kses_post')->once()->with('Hello <script>x</script>')->andReturn('Hello x');

        \ob_start();
        (new $class('Hello <script>x</script>'))->render();
        $output = \ob_get_clean();

        $this->assertStringContainsString('<p>Hello x</p>', $output);
    }
}
