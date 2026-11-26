<?php

/**
 * @var \Coretik\Services\Migrations\Admin $admin
 * @var array<int, array{key: string, description: string, seeder: bool, status: array{status: string, ran_at: int, message: string}|null}> $rows
 * @var string[] $pending
 * @var \Coretik\Services\Migrations\Result[] $results
 */

use Coretik\Services\Migrations\Admin;
use Coretik\Services\Migrations\Result;

$tones = [Result::DONE => 'success', Result::FAILED => 'error', Result::SKIPPED => 'warning'];
?>
<div class="wrap coretik-migrations">
    <h1 class="wp-heading-inline"><?= \esc_html($admin->option('title')) ?></h1>
    <?php if ($pending) : ?>
        <a class="page-title-action" href="<?= \esc_url($admin->actionUrl('migrate')) ?>">
            Lancer les migrations en attente (<?= count($pending) ?>)
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php foreach ($results as $result) : ?>
        <div class="notice notice-<?= \esc_attr($tones[$result->status] ?? 'info') ?>">
            <p>
                <code><?= \esc_html($result->key) ?></code>
                <strong><?= \esc_html(Admin::STATUS_LABELS[$result->status] ?? $result->status) ?></strong>
                <?= $result->message ? ' : ' . \esc_html($result->message) : '' ?>
            </p>
            <?php if ($result->logs) : ?>
                <ul>
                    <?php foreach ($result->logs as $log) : ?>
                        <li><?= \wp_kses_post($log['message'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p>
        Une migration n'est lancée qu'une fois ; un seeder peut l'être autant de fois que nécessaire.
        En ligne de commande : <code>wp app migrate</code> (migrations en attente), <code>wp app migrate &lt;clé&gt;</code>, <code>wp app migrate --list</code>.
    </p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col">Clé</th>
                <th scope="col">Description</th>
                <th scope="col">Type</th>
                <th scope="col">Dernière exécution</th>
                <th scope="col"><span class="screen-reader-text">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="5">Aucune migration déclarée.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <?php
                $status = $row['status'];
                $done = Result::DONE === ($status['status'] ?? null);
                $again = $done && !$row['seeder'];
                ?>
                <tr>
                    <td><code><?= \esc_html($row['key']) ?></code></td>
                    <td><?= \esc_html($row['description']) ?></td>
                    <td><?= $row['seeder'] ? 'Seeder' : 'Migration' ?></td>
                    <td>
                        <?php if ($status) : ?>
                            <strong><?= \esc_html(Admin::STATUS_LABELS[$status['status']] ?? $status['status']) ?></strong>
                            le <?= \esc_html(\wp_date('d/m/Y H:i:s', (int)$status['ran_at'])) ?>
                            <?php if ($status['message']) : ?>
                                <br><small><?= \esc_html($status['message']) ?></small>
                            <?php endif; ?>
                        <?php else : ?>
                            <?= $row['seeder'] ? '—' : 'En attente' ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a
                            class="button<?= $again ? '' : ' button-primary' ?>"
                            href="<?= \esc_url($admin->actionUrl('run', ['key' => $row['key'], 'force' => $again ? 1 : null])) ?>"
                            onclick="return confirm(<?= \esc_attr((string)\wp_json_encode(sprintf('Lancer %s ?', $row['key']))) ?>)"
                        ><?= $again ? 'Relancer' : 'Lancer' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
