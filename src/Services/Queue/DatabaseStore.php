<?php

namespace Coretik\Services\Queue;

/**
 * Jobs in the table {prefix}coretik_jobs, created (or upgraded) on first use.
 * A job is claimed by an UPDATE … WHERE status = 'pending': concurrent workers never run the same job.
 */
class DatabaseStore implements StoreInterface
{
    public const TABLE = 'coretik_jobs';
    public const VERSION = '1';
    public const VERSION_OPTION = 'coretik_queue_db_version';

    /** @var array<string, true> Tables checked in this request */
    protected static array $installed = [];

    public function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . static::TABLE;
    }

    /**
     * Creates or upgrades the table when its version changed
     */
    public function install(bool $force = false): void
    {
        global $wpdb;

        $table = $this->table();
        if (!$force && (isset(static::$installed[$table]) || static::VERSION === \get_option(static::VERSION_OPTION))) {
            static::$installed[$table] = true;
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta(): two spaces after PRIMARY KEY, one key per line
        \dbDelta(sprintf(
            "CREATE TABLE %s (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  queue varchar(64) NOT NULL DEFAULT 'default',
  job varchar(191) NOT NULL,
  payload longtext NOT NULL,
  job_group varchar(191) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  available_at bigint(20) unsigned NOT NULL DEFAULT 0,
  reserved_at bigint(20) unsigned DEFAULT NULL,
  created_at bigint(20) unsigned NOT NULL DEFAULT 0,
  finished_at bigint(20) unsigned DEFAULT NULL,
  error text,
  PRIMARY KEY  (id),
  KEY status_available (status,available_at),
  KEY job_group (job_group)
) %s;",
            $table,
            $wpdb->get_charset_collate()
        ));

        \update_option(static::VERSION_OPTION, static::VERSION, true);
        static::$installed[$table] = true;
    }

    public function push(string $queue, string $job, array $payload, int $availableAt, ?string $group = null): int
    {
        global $wpdb;
        $this->install();

        $wpdb->insert($this->table(), [
            'queue' => $queue,
            'job' => $job,
            'payload' => (string)\wp_json_encode($payload),
            'job_group' => $group,
            'status' => QueuedJob::PENDING,
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => time(),
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']);

        return (int)$wpdb->insert_id;
    }

    public function reserve(int $now, ?string $queue = null): ?QueuedJob
    {
        global $wpdb;
        $this->install();
        $table = $this->table();

        // Another worker may claim the candidate first: try the next ones
        for ($i = 0; $i < 5; $i++) {
            $sql = "SELECT id FROM {$table} WHERE status = %s AND available_at <= %d";
            $args = [QueuedJob::PENDING, $now];
            if (null !== $queue) {
                $sql .= ' AND queue = %s';
                $args[] = $queue;
            }
            $id = (int)$wpdb->get_var($wpdb->prepare($sql . ' ORDER BY available_at, id LIMIT 1', ...$args));
            if (!$id) {
                return null;
            }

            $claimed = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = %s, reserved_at = %d, attempts = attempts + 1 WHERE id = %d AND status = %s",
                QueuedJob::RUNNING,
                $now,
                $id,
                QueuedJob::PENDING
            ));
            if (1 === $claimed) {
                return $this->find($id);
            }
        }

        return null;
    }

    public function find(int $id): ?QueuedJob
    {
        global $wpdb;
        $this->install();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id));
        return $row ? $this->hydrate($row) : null;
    }

    public function complete(int $id, int $now): void
    {
        $this->update($id, ['status' => QueuedJob::DONE, 'finished_at' => $now, 'reserved_at' => null, 'error' => null]);
    }

    public function release(int $id, int $availableAt, string $error): void
    {
        $this->update($id, ['status' => QueuedJob::PENDING, 'available_at' => $availableAt, 'reserved_at' => null, 'error' => $error]);
    }

    public function fail(int $id, int $now, string $error): void
    {
        $this->update($id, ['status' => QueuedJob::FAILED, 'finished_at' => $now, 'reserved_at' => null, 'error' => $error]);
    }

    public function releaseStale(int $reservedBefore, int $now): int
    {
        global $wpdb;
        $this->install();

        return (int)$wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET status = %s, reserved_at = NULL, available_at = %d, error = %s WHERE status = %s AND reserved_at < %d",
            QueuedJob::PENDING,
            $now,
            'Worker stopped while the job was running (timeout, fatal error…)',
            QueuedJob::RUNNING,
            $reservedBefore
        ));
    }

    public function retry(int $now, ?string $group = null, ?int $id = null): int
    {
        global $wpdb;
        $this->install();

        $sql = "UPDATE {$this->table()} SET status = %s, attempts = 0, available_at = %d, finished_at = NULL, error = NULL WHERE status = %s";
        $args = [QueuedJob::PENDING, $now, QueuedJob::FAILED];
        if (null !== $group) {
            $sql .= ' AND job_group = %s';
            $args[] = $group;
        }
        if (null !== $id) {
            $sql .= ' AND id = %d';
            $args[] = $id;
        }

        return (int)$wpdb->query($wpdb->prepare($sql, ...$args));
    }

    public function stats(?string $group = null): array
    {
        global $wpdb;
        $this->install();

        $sql = "SELECT status, COUNT(*) AS total FROM {$this->table()}";
        $sql = null === $group ? $sql . ' GROUP BY status' : $wpdb->prepare($sql . ' WHERE job_group = %s GROUP BY status', $group);

        $stats = array_fill_keys(QueuedJob::STATUSES, 0);
        foreach ((array)$wpdb->get_results($sql) as $row) {
            $stats[$row->status] = (int)$row->total;
        }

        return $stats;
    }

    public function purge(int $doneBefore, int $failedBefore): int
    {
        global $wpdb;
        $this->install();

        return (int)$wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE (status = %s AND finished_at < %d) OR (status = %s AND finished_at < %d)",
            QueuedJob::DONE,
            $doneBefore,
            QueuedJob::FAILED,
            $failedBefore
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function update(int $id, array $data): void
    {
        global $wpdb;
        $this->install();

        $wpdb->update($this->table(), $data, ['id' => $id]);
    }

    protected function hydrate(object $row): QueuedJob
    {
        $payload = json_decode((string)$row->payload, true);

        return new QueuedJob(
            id: (int)$row->id,
            queue: (string)$row->queue,
            job: (string)$row->job,
            payload: is_array($payload) ? $payload : [],
            status: (string)$row->status,
            attempts: (int)$row->attempts,
            availableAt: (int)$row->available_at,
            createdAt: (int)$row->created_at,
            group: null !== $row->job_group ? (string)$row->job_group : null,
            reservedAt: null !== $row->reserved_at ? (int)$row->reserved_at : null,
            finishedAt: null !== $row->finished_at ? (int)$row->finished_at : null,
            error: null !== $row->error ? (string)$row->error : null
        );
    }
}
