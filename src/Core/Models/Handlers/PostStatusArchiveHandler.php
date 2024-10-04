<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Models\Interfaces\ModelInterface;
use DateTime;
use Exception;

class PostStatusArchiveHandler extends Handler
{
    const POST_STATUS_ARCHIVE_NAME = 'archive';
    const POST_STATUS_ARCHIVE_LABEL = 'Archivé';

    private array $scheduler = [];
    private $modelIsArchivable;
    private static $registered = false;

    /**
     * Create a fresh handler instance and set the scheduler
     */
    public static function withScheduler(string|DateTime $datetime, string $recurrence = 'daily'): self
    {
        $instance = new static();
        $instance->setScheduler($datetime, $recurrence);
        return $instance;
    }

    /**
     * Create a fresh handler instance and set the model policy
     */
    public static function withModelPolicy(callable $policy): self
    {
        $instance = new static();
        $instance->setModelPolicy($policy);
        return $instance;
    }

    public function actions(): void
    {
        $this->builder->attach('archive', [$this, 'archive']);
        $this->builder->attach('searchAndArchive', [$this, 'archiver']);
        $this->registerArchivePostStatus();
        \add_action('admin_footer-post.php', [$this, 'hookDisplayArchiveStatus']);

        if (!empty($this->scheduler)) {
            $this->schedule(...$this->scheduler);
        }
    }

    public function freeze(): void
    {
        $this->builder->attach('archive', fn () => null);
        \remove_action('admin_footer-post.php', [$this, 'hookDisplayArchiveStatus']);
    }

    /**
     * Schedule an auto archive event.
     * @param DateTime $datetime The first single event date to run
     * @param string $recurrence
     */
    public function setScheduler(string|DateTime $datetime, string $recurrence = 'daily')
    {
        $this->scheduler = [
            $datetime,
            $recurrence,
        ];
        return $this;
    }

    /**
     * @param ?callable $policy A callback with $model as parameter who return a boolean
     */
    public function setModelPolicy(callable $policy)
    {
        $this->modelIsArchivable = $policy;
        return $this;
    }

    public function schedule(string|DateTime $datetime, string $recurrence = 'daily')
    {
        if (\is_string($datetime)) {
            $datetime = new DateTime($datetime, app()->get('timezone'));
        }

        $hook = 'coretik/handler/post_status_archive/schedule/' . $this->builder->getName() . '/auto_status_archived';
        if (!\wp_next_scheduled($hook)) {
            \wp_schedule_event($datetime->getTimestamp(), $recurrence, $hook);
        }

        \add_action($hook, [$this, 'archiver']);
    }

    public function archiver()
    {
        if (!isset($this->modelIsArchivable) && !\method_exists($this->builder->query(), 'archivable')) {
            throw new Exception(sprintf(
                'You have to provide an "archivable" method into %s querier or provide a model policy callback applied on each published %s posts."',
                $this->builder->getName(),
                $this->builder->getName(),
            ));
        }

        $query = $this->builder->query();

        if (\method_exists($query, 'archivable')) {
            $query->archivable();
        } else {
            $query
                ->all()
                ->set('post_status', 'publish');
        }

        $collection = $query->collection();

        if (!empty($this->modelIsArchivable)) {
            $collection = $collection->filter($this->modelIsArchivable);
        }

        $collection->each(fn ($object) => $this->archive($object));
    }

    public function archive(int|ModelInterface $target)
    {
        if ($target instanceof ModelInterface) {
            $target = $target->id();
        }

        \wp_update_post(['ID' => $target, 'post_status' => static::POST_STATUS_ARCHIVE_NAME]);
    }

    public function hookDisplayArchiveStatus()
    {
        global $post;
        $complete = '';

        if (!$this->builder->concern((int)$post->ID)) {
            return;
        }

        $model = $this->builder->model((int)$post->ID, $post);

        if (static::POST_STATUS_ARCHIVE_NAME === $model->post_status) {
            $complete = ' selected=\"selected\"';
        }

        echo '<script>' .
                'jQuery(document).ready(function($){' .
                    '$("select#post_status").append(' .
                        '"<option value=\"' . static::POST_STATUS_ARCHIVE_NAME . '\" ' . $complete . '>' .
                            static::POST_STATUS_ARCHIVE_LABEL .
                        '</option>"' .
                    ');' .
                    (!empty($complete)
                        ? '$("#post-status-display").html("' . static::POST_STATUS_ARCHIVE_LABEL . '");'
                        : '' ) .
                '});' .
            '</script>';
    }

    public function registerArchivePostStatus()
    {
        if (static::$registered) {
            return;
        }

        \register_post_status(self::POST_STATUS_ARCHIVE_NAME, [
            'label'                     => static::POST_STATUS_ARCHIVE_LABEL,
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                static::POST_STATUS_ARCHIVE_LABEL . ' <span class="count">(%s)</span> ',
                static::POST_STATUS_ARCHIVE_LABEL . ' <span class="count">(%s)</span> '
            ),
        ]);

        static::$registered = true;
    }
}
