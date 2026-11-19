<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Trait;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

/**
 * A site folder of the test's own under the system's temporary folder, with the add-on's config folder, removed
 * again whatever the test left in it.
 */
trait UsesTemporarySiteTrait
{
    private string $site;

    protected function setUpSite(): void
    {
        $this->site = sprintf('%s/campaign-monitor-site-%s', sys_get_temp_dir(), uniqid());
        mkdir($this->extensions(), permissions: 0o777, recursive: true);
    }

    protected function tearDownSite(): void
    {
        if (! is_dir($this->site)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->site, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            chmod($entry->getPathname(), permissions: 0o777);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->site);
    }

    /**
     * Writes the add-on's $kind (global or local) config file, as a file returning $config.
     *
     * @param array<array-key, mixed> $config
     */
    private function configFile(string $kind, array $config): void
    {
        file_put_contents(
            sprintf('%s/campaign-monitor.%s.php', $this->extensions(), $kind),
            '<?php return ' . var_export($config, return: true) . ';',
        );
    }

    private function extensions(): string
    {
        return "{$this->site}/config/autoload/extensions";
    }
}
