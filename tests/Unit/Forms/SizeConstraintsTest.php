<?php

namespace Coretik\Tests\Unit\Forms;

use Coretik\Services\Forms\Core\Validation\Constraints\MaxSize;
use Coretik\Services\Forms\Core\Validation\Constraints\MinSize;
use Coretik\Tests\TestCase;

/**
 * min-size / max-size count characters, not bytes: accented values (UTF-8) are measured as the browser maxlength does
 */
class SizeConstraintsTest extends TestCase
{
    public function testMaxSizeCountsCharacters(): void
    {
        $max = new MaxSize(12);

        $this->assertTrue($max->validate('name', "Zoé-Lou D'Ax", []), '12 characters, 13 bytes');
        $this->assertTrue($max->validate('name', 'Éléonore', []));
        $this->assertFalse($max->validate('name', 'Maximilienne-C', []));
        $this->assertTrue($max->validate('name', '', []), 'Empty: required decides');
    }

    public function testMinSizeCountsCharacters(): void
    {
        $min = new MinSize(4);

        $this->assertFalse($min->validate('name', 'Zoé', []), '3 characters, 4 bytes');
        $this->assertTrue($min->validate('name', 'Zoéa', []));
        $this->assertTrue($min->validate('name', '', []), 'Empty: required decides');
    }
}
