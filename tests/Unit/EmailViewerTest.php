<?php

namespace Coretik\Tests\Unit;

use Coretik\Services\Email\Viewer\Entry;
use Coretik\Services\Email\Viewer\Preview;
use Coretik\Services\Email\Viewer\Viewer;
use Coretik\Tests\TestCase;

class EmailViewerTest extends TestCase
{
    private function entries(): array
    {
        return [
            new Entry('base', 'Socle', 'Socle'),
            new Entry('reset', 'Mot de passe oublié', 'Compte'),
            new Entry('free', 'Email libre', 'Dossier'),
            new Entry('invite', 'Invitation', 'Compte'),
        ];
    }

    public function testGroupsKeepTheCatalogOrder(): void
    {
        $groups = Viewer::groups($this->entries());

        $this->assertSame(['Socle', 'Compte', 'Dossier'], array_keys($groups));
        $this->assertSame(['reset', 'invite'], array_map(fn ($entry) => $entry->key, $groups['Compte']));
    }

    public function testResolveFallsBackToTheFirstEntry(): void
    {
        $this->assertSame('free', Viewer::resolve($this->entries(), 'free')?->key);
        $this->assertSame('base', Viewer::resolve($this->entries(), 'unknown')?->key);
        $this->assertNull(Viewer::resolve([], 'free'));
    }

    public function testPreviewHeaders(): void
    {
        $preview = new Preview('Objet', ['a@example.com', 'B <b@example.com>'], '<p></p>', [
            'From: Site <noreply@example.com>',
            'reply-to: contact@example.com',
        ]);

        $this->assertSame('Site <noreply@example.com>', $preview->header('from'));
        $this->assertSame('contact@example.com', $preview->header('Reply-To'));
        $this->assertNull($preview->header('Cc'));
        $this->assertSame('a@example.com, B <b@example.com>', $preview->recipients());
    }
}
