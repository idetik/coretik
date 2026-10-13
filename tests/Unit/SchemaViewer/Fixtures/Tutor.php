<?php

namespace Coretik\Tests\Unit\SchemaViewer\Fixtures;

use Coretik\Core\Models\Model;

class Tutor extends Model
{
    public function players()
    {
        return $this->hasMany(Player::class);
    }
}
