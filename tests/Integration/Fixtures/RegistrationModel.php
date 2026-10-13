<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Wp\PostModel;

/**
 * The player_id meta is mounted on post_parent (BelongsToHandler)
 */
class RegistrationModel extends PostModel
{
    protected function initializeModel(): void
    {
        $this->declareMetas(['player_id' => 'player_id']);
    }

    public function player(): ?ModelInterface
    {
        return $this->belongsTo(PlayerModel::class);
    }

    public function tutor(): ?ModelInterface
    {
        return $this->belongsToThrough(TutorModel::class, PlayerModel::class);
    }
}
