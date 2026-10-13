<?php

namespace Coretik\Tests\Unit\SchemaViewer\Fixtures;

use Coretik\Core\Models\Model;
use Coretik\Tests\Unit\SchemaViewer\Fixtures\Player as Stagiaire;

class Registration extends Model
{
    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function player()
    {
        // Aliased import
        return $this->belongsTo(Stagiaire::class);
    }
}
