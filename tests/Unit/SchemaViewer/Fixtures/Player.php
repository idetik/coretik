<?php

namespace Coretik\Tests\Unit\SchemaViewer\Fixtures;

use Coretik\Core\Models\Model;

class Player extends Model
{
    public function tutor()
    {
        // Builder name instead of a model class
        return $this->belongsTo('tutor');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}
