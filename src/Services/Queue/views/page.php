<?php

/**
 * @var \Coretik\Services\Queue\Admin $admin
 * @var array{status: string, queue: string, group: string} $filters
 * @var \Coretik\Services\Queue\QueuedJob[] $jobs
 * @var array<string, int> $stats
 * @var string[] $queues
 * @var array{last: ?int, next: int|false, late: bool, cron_disabled: bool} $health
 * @var int $total
 * @var int $paged
 * @var int $pages
 * @var string $notice action done (retried, deleted, worked)
 * @var int $count
 */

use Coretik\Services\Queue\Admin;
use Coretik\Services\Queue\QueuedJob;

$notices = [
    'retried' => sprintf('%d tâche(s) remise(s) en file.', $count),
    'deleted' => $count ? 'Tâche supprimée.' : 'La tâche n\'a pas pu être supprimée (en cours d\'exécution ?).',
    'worked' => sprintf('%d tâche(s) exécutée(s).', $count),
];
?>
<div class="wrap coretik-queue">
    <h1 class="wp-heading-inline"><?= \esc_html($admin->option('title')) ?></h1>
    <a class="page-title-action" href="<?= \esc_url($admin->actionUrl('work', $filters)) ?>">Exécuter maintenant</a>
    <?php if ($stats[QueuedJob::FAILED]) : ?>
        <a class="page-title-action" href="<?= \esc_url($admin->actionUrl('retry', ['group' => $filters['group']])) ?>">
            Relancer les échecs<?= $filters['group'] ? ' du groupe' : '' ?> (<?= (int)$stats[QueuedJob::FAILED] ?>)
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if (isset($notices[$notice])) : ?>
        <div class="notice notice-success is-dismissible"><p><?= \esc_html($notices[$notice]) ?></p></div>
    <?php endif; ?>

    <?php
    if ($health['late']) {
        [$tone, $state] = ['late', 'Le worker ne semble pas tourner'];
    } elseif ($health['last']) {
        [$tone, $state] = ['ok', 'Worker actif'];
    } else {
        [$tone, $state] = ['idle', 'Worker pas encore lancé'];
    }
    $moment = fn (?int $timestamp, string $empty, ?string $label = null, string $modifier = '') => $timestamp
        ? sprintf(
            '<strong class="coretik-queue__health-value%s"><time datetime="%s">%s</time></strong><span class="coretik-queue__health-hint">%s</span>',
            $modifier ? ' coretik-queue__health-value--' . \esc_attr($modifier) : '',
            \esc_attr(gmdate('c', $timestamp)),
            \esc_html($label ?? Admin::relative($timestamp)),
            \esc_html(($label ? 'prévu ' : '') . Admin::fullDate($timestamp))
        )
        : sprintf('<strong class="coretik-queue__health-value">%s</strong>', \esc_html($empty));

    // Next run already due: WP-Cron runs it at the next request (or the next server cron)
    $next = $health['next'] ? (int)$health['next'] : null;
    $overdue = $next && $next <= time();
    $nextLabel = !$overdue ? null : (time() - $next > (int)$admin->option('late_after') ? 'En retard' : 'Imminent');
    ?>
    <section class="coretik-queue__health coretik-queue__health--<?= \esc_attr($tone) ?>" aria-label="État du worker">
        <p class="coretik-queue__health-state">
            <span class="coretik-queue__health-dot" aria-hidden="true"></span>
            <strong><?= \esc_html($state) ?></strong>
            <?php if ($health['late']) : ?>
                <span>
                    <?= (int)$stats[QueuedJob::PENDING] ?> tâche(s) en attente.
                    Vérifiez la tâche cron du serveur (wp-cron toutes les minutes).
                </span>
            <?php endif; ?>
        </p>
        <dl class="coretik-queue__health-grid">
            <div class="coretik-queue__health-item">
                <dt><span class="dashicons dashicons-backup" aria-hidden="true"></span>Dernier passage</dt>
                <dd><?= $moment($health['last'], 'jamais') ?></dd>
            </div>
            <div class="coretik-queue__health-item">
                <dt><span class="dashicons dashicons-clock" aria-hidden="true"></span>Prochain passage</dt>
                <dd><?= $moment($next, 'non planifié', $nextLabel, 'En retard' === $nextLabel ? 'late' : '') ?></dd>
            </div>
            <div class="coretik-queue__health-item">
                <dt><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>Déclenchement</dt>
                <dd>
                    <strong class="coretik-queue__health-value"><?= $health['cron_disabled'] ? 'Cron serveur' : 'Visites du site' ?></strong>
                    <span class="coretik-queue__health-hint"><?= $health['cron_disabled'] ? 'DISABLE_WP_CRON actif, toutes les minutes' : 'et tâche cron du serveur si configurée' ?></span>
                </dd>
            </div>
        </dl>
    </section>

    <ul class="subsubsub">
        <li>
            <a href="<?= \esc_url($admin->url(['status' => null] + $filters)) ?>" class="<?= '' === $filters['status'] ? 'current' : '' ?>">
                Toutes <span class="count">(<?= (int)array_sum($stats) ?>)</span>
            </a> |
        </li>
        <?php foreach (Admin::STATUS_LABELS as $status => $label) : ?>
            <li>
                <a href="<?= \esc_url($admin->url(['status' => $status] + $filters)) ?>" class="<?= $status === $filters['status'] ? 'current' : '' ?>">
                    <?= \esc_html($label) ?> <span class="count">(<?= (int)$stats[$status] ?>)</span>
                </a><?= QueuedJob::FAILED !== $status ? ' |' : '' ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <form class="coretik-queue__filters" method="get" action="<?= \esc_url(\admin_url($admin->option('parent') && str_ends_with((string)$admin->option('parent'), '.php') ? (string)$admin->option('parent') : 'admin.php')) ?>">
        <input type="hidden" name="page" value="<?= \esc_attr($admin->option('slug')) ?>">
        <?php if ($filters['status']) : ?>
            <input type="hidden" name="status" value="<?= \esc_attr($filters['status']) ?>">
        <?php endif; ?>
        <label class="screen-reader-text" for="coretik-queue-queue">File</label>
        <select id="coretik-queue-queue" name="queue">
            <option value="">Toutes les files</option>
            <?php foreach ($queues as $queue) : ?>
                <option value="<?= \esc_attr($queue) ?>" <?php \selected($queue, $filters['queue']); ?>><?= \esc_html($queue) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="screen-reader-text" for="coretik-queue-group">Groupe</label>
        <input type="search" id="coretik-queue-group" name="group" value="<?= \esc_attr($filters['group']) ?>" placeholder="Groupe (exact)">
        <button type="submit" class="button">Filtrer</button>
        <?php if ($filters['queue'] || $filters['group']) : ?>
            <a class="button-link" href="<?= \esc_url($admin->url(['status' => $filters['status']])) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <table class="wp-list-table widefat fixed striped coretik-queue__table">
        <thead>
            <tr>
                <th scope="col" class="column-id">#</th>
                <th scope="col">Tâche</th>
                <th scope="col" class="column-status">Statut</th>
                <th scope="col" class="column-attempts">Tentatives</th>
                <th scope="col">Dates</th>
                <th scope="col">Dernière erreur</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$jobs) : ?>
                <tr><td colspan="6">Aucune tâche.</td></tr>
            <?php endif; ?>
            <?php foreach ($jobs as $job) : ?>
                <tr>
                    <td class="column-id"><?= (int)$job->id ?></td>
                    <td>
                        <strong title="<?= \esc_attr($job->job) ?>"><?= \esc_html(Admin::jobName($job->job)) ?></strong>
                        <div class="coretik-queue__meta">
                            File <a href="<?= \esc_url($admin->url(['queue' => $job->queue] + $filters)) ?>"><?= \esc_html($job->queue) ?></a>
                            <?php if ($job->group) : ?>
                                · groupe <a href="<?= \esc_url($admin->url(['group' => $job->group] + $filters)) ?>"><?= \esc_html($job->group) ?></a>
                            <?php endif; ?>
                        </div>
                        <details class="coretik-queue__payload">
                            <summary>Données</summary>
                            <pre><?= \esc_html((string)\wp_json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                        <div class="row-actions visible">
                            <?php if (QueuedJob::FAILED === $job->status) : ?>
                                <a href="<?= \esc_url($admin->actionUrl('retry', ['id' => $job->id] + $filters)) ?>">Relancer</a>
                            <?php endif; ?>
                            <?php if (QueuedJob::RUNNING !== $job->status) : ?>
                                <?= QueuedJob::FAILED === $job->status ? ' | ' : '' ?>
                                <span class="trash"><a href="<?= \esc_url($admin->actionUrl('delete', ['id' => $job->id] + $filters)) ?>">Supprimer</a></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="column-status">
                        <span class="coretik-queue__status coretik-queue__status--<?= \esc_attr($job->status) ?>">
                            <?= \esc_html(Admin::JOB_STATUS_LABELS[$job->status] ?? $job->status) ?>
                        </span>
                    </td>
                    <td class="column-attempts"><?= (int)$job->attempts ?></td>
                    <td class="coretik-queue__dates">
                        Créée <?= \esc_html(Admin::date($job->createdAt)) ?><br>
                        <?php if (QueuedJob::PENDING === $job->status) : ?>
                            Disponible <?= \esc_html(Admin::date($job->availableAt)) ?>
                        <?php elseif (QueuedJob::RUNNING === $job->status) : ?>
                            Démarrée <?= \esc_html(Admin::date($job->reservedAt)) ?>
                        <?php else : ?>
                            Terminée <?= \esc_html(Admin::date($job->finishedAt)) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $job->error ? '<code class="coretik-queue__error">' . \esc_html($job->error) . '</code>' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?= (int)$total ?> tâche(s)</span>
                <?= \wp_kses_post((string)\paginate_links([
                    'base' => \add_query_arg('paged', '%#%', $admin->url($filters)),
                    'format' => '',
                    'current' => $paged,
                    'total' => $pages,
                ])) ?>
            </div>
        </div>
    <?php endif; ?>
</div>
