<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Core\Validation\Constraints\File;
use Coretik\Tests\TestCase;

class FileConstraintTest extends TestCase
{
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('wp_parse_args')->alias(fn ($args, $defaults) => \array_merge($defaults, $args));
        Functions\when('size_format')->alias(fn ($bytes) => ($bytes / 1024 / 1024) . ' MB');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @\unlink($file);
        }
        $_FILES = [];
        parent::tearDown();
    }

    /**
     * Simulate an uploaded file: $content is written to a temporary file, $name is the client file name.
     */
    private ?bool $valid = null;

    private function validate(string $name, string $content, array $args): File
    {
        $path = \tempnam(\sys_get_temp_dir(), 'coretik');
        \file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        $_FILES['coretik-form'] = [
            'name' => ['upload' => $name],
            'tmp_name' => ['upload' => $path],
            'error' => ['upload' => UPLOAD_ERR_OK],
            'size' => ['upload' => $args['size'] ?? \strlen($content)],
        ];
        unset($args['size']);

        $constraint = new File($args, null);
        $this->valid = $constraint->validate('upload', null, []);
        return $constraint;
    }

    public static function validFiles(): array
    {
        return [
            'csv' => ['data.csv', "name,email\nJohn,john@example.com\n", ['csv']],
            'pdf' => ['doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n", ['pdf']],
            'uppercase extension' => ['IMAGE.PNG', \base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='), ['png']],
        ];
    }

    /**
     * @dataProvider validFiles
     */
    public function testValidFileIsAccepted(string $name, string $content, array $types): void
    {
        $this->validate($name, $content, ['types' => $types]);
        $this->assertTrue($this->valid);
    }

    public static function invalidFiles(): array
    {
        return [
            'php file' => ['shell.php', "<?php echo 'x';", ['csv']],
            'php code named csv' => ['data.csv', "<?php system(\$_GET['c']);", ['csv']],
            'double extension' => ['shell.php.csv', "a,b\n1,2\n", ['csv']],
            'binary named csv' => ['data.csv', \random_bytes(512) . "\x00\x01\x02", ['csv']],
            'extension not allowed' => ['data.txt', "a,b\n1,2\n", ['csv']],
            'no extension' => ['data', "a,b\n1,2\n", ['csv']],
            'content does not match extension' => ['doc.pdf', "a,b\n1,2\n", ['csv', 'pdf']],
        ];
    }

    /**
     * @dataProvider invalidFiles
     */
    public function testInvalidFileIsRejected(string $name, string $content, array $types): void
    {
        $constraint = $this->validate($name, $content, ['types' => $types]);

        $this->assertFalse($this->valid);
        $this->assertSame('Format du fichier invalide.', $constraint->getMessage());
    }

    public function testNoAllowedTypeRejectsFile(): void
    {
        $this->validate('data.csv', "a,b\n", []);
        $this->assertFalse($this->valid);
    }

    public function testUnknownAllowedTypeRejectsFile(): void
    {
        $this->validate('data.foo', "a,b\n", ['types' => ['foo']]);
        $this->assertFalse($this->valid);
    }

    public function testTooLargeFileMessageIsReadable(): void
    {
        $constraint = $this->validate('data.csv', "a,b\n", ['types' => ['csv'], 'max-size' => 2 * 1024 * 1024, 'size' => 3 * 1024 * 1024]);

        $this->assertFalse($this->valid);
        $this->assertSame('Le fichier doit être inférieur à 2 MB.', $constraint->getMessage());
    }
}
