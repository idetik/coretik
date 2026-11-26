<?php

namespace Coretik\Tests\Unit\SchemaViewer\Fixtures;

use Coretik\Core\Models\Model;

class Camp extends Model
{
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}
