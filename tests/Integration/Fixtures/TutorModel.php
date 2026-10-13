<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Wp\UserModel;
use Coretik\Core\Query\Interfaces\QuerierInterface;

class TutorModel extends UserModel
{
    public function players(): Collection|QuerierInterface
    {
        return $this->hasMany(PlayerModel::class);
    }

    public function registrations(bool $collect = true): Collection|QuerierInterface
    {
        return $this->hasManyThrough(RegistrationModel::class, PlayerModel::class, $collect);
    }

    public function lastRegistration(): ?ModelInterface
    {
        return $this->hasOneThrough(RegistrationModel::class, PlayerModel::class);
    }
}
