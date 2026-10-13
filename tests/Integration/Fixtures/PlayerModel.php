<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Query\Interfaces\QuerierInterface;

/**
 * The tutor_id meta is mounted on post_author (BelongsToHandler)
 */
class PlayerModel extends PostModel
{
    protected function initializeModel(): void
    {
        $this->declareMetas(['tutor_id' => 'tutor_id']);
    }

    public function tutor(): ?ModelInterface
    {
        return $this->belongsTo(TutorModel::class);
    }

    public function registrations(): Collection|QuerierInterface
    {
        return $this->hasMany(RegistrationModel::class);
    }

    public function camps(): Collection|QuerierInterface
    {
        return $this->belongsToMany(CampModel::class, RegistrationModel::class);
    }
}
