<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Services\Email\Builder;
use Coretik\Services\Email\Email;

/**
 * Email with the templates of tests/Integration/Fixtures/emails (no theme in the test suite)
 */
class FixtureEmail extends Email
{
    public string $preheader = '';

    public function __construct()
    {
        parent::__construct(new Builder(), __DIR__ . '/emails');
    }

    public function useTemplate(string $template, array $data = [])
    {
        $this->message = $this->includeFile($this->templatesDir . '/' . $template . '.php', $data + ['email' => $this]);
        return $this;
    }

    public function html()
    {
        return $this->includeFile($this->templatesDir . '/layout.php', ['email' => $this]);
    }

    private function includeFile(string $file, array $data): string
    {
        ob_start();
        extract($data);
        include $file;
        return (string)ob_get_clean();
    }
}
