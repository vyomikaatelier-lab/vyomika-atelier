<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicHtaccessTest extends TestCase
{
    private function htaccessRules(): string
    {
        $path = public_path('.htaccess');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_htaccess_allows_public_storage_symlink_paths(): void
    {
        $htaccess = $this->htaccessRules();

        $this->assertStringNotContainsString(
            '^(docs|storage|database',
            $htaccess,
            'Blanket /storage/ block would deny public/storage symlink media URLs'
        );
        $this->assertStringContainsString(
            'storage/(app|framework|logs',
            $htaccess,
            'Private storage trees must remain blocked'
        );
    }

    public function test_htaccess_denies_sensitive_path_patterns(): void
    {
        $htaccess = $this->htaccessRules();

        $this->assertStringContainsString('\.env', $htaccess);
        $this->assertStringContainsString('^(docs|database|tests|vendor', $htaccess);
        $this->assertStringContainsString('\.(sql|md|log|lock|map|bak|backup|jsonl)', $htaccess);
        $this->assertStringContainsString('composer\.(json|lock|phar)', $htaccess);
    }

    public function test_public_storage_media_files_are_reachable(): void
    {
        $storageRoot = public_path('storage');
        if (! is_dir($storageRoot)) {
            $this->markTestSkipped('public/storage symlink not present in this environment');
        }

        $sample = $this->firstPublicStorageFile($storageRoot);
        if ($sample === null) {
            $this->markTestSkipped('No files under public/storage to probe');
        }

        $relative = str_replace('\\', '/', substr($sample, strlen(public_path()) + 1));
        $this->get('/'.$relative)->assertOk();
    }

    private function firstPublicStorageFile(string $dir): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file->getFilename())) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
