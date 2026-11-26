<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Query\Interfaces\QuerierInterface;

/**
 * Registrations belong to a camp through a shadow taxonomy (their post_parent is the player)
 */
class CampModel extends PostModel
{
    public function registrations(bool $collect = true): Collection|QuerierInterface
    {
        return $this->hasMany(RegistrationModel::class, $collect);
    }

    public function firstRegistration(): ?ModelInterface
    {
        return $this->hasOne(RegistrationModel::class);
    }

    public function players(?callable $constraint = null, bool $collect = true): Collection|QuerierInterface
    {
        return $this->belongsToMany(PlayerModel::class, RegistrationModel::class, $constraint, $collect);
    }
}
