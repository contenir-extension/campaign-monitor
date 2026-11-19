<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Sync;

use Contenir\Extension\Api\Handle;
use Random\RandomException;

use function dirname;
use function file_put_contents;
use function function_exists;
use function is_array;
use function is_dir;
use function is_file;
use function is_writable;
use function opcache_invalidate;
use function rename;
use function sprintf;
use function uniqid;
use function var_export;

/**
 * Adds a fresh pepper to the add-on's local config file, config/autoload/extensions/<handle>.local.php, where the
 * CMS and the site both read it. It only ever adds one: a pepper already set, in either file, is never replaced,
 * since a new pepper would forget everyone who unsubscribed. The file's other values are kept; it is written whole,
 * so any comments in it are not.
 *
 * @api
 */
final readonly class PepperFile
{
    public function __construct(
        private string $sitePath,
        private Handle $handle,
        private Secrets $secrets = new Secrets(),
    ) {}

    /**
     * Writes a fresh pepper into the local config file, making the file when there is none.
     *
     * @throws NotReady When a pepper is already set, a file can't be read, or the file can't be written.
     * @throws RandomException When no pepper can be made.
     */
    public function add(): void
    {
        if (null !== SiteConfig::read($this->sitePath, $this->handle)->pepper) {
            throw NotReady::because(
                'A pepper is already set. It is never replaced, since a new one would forget everyone who unsubscribed.',
            );
        }

        $path   = $this->path();
        $config = $this->current($path);
        /** @var array<string, array<array-key, mixed>> $extensions */
        $extensions                                 = $config['extensions'] ?? [];
        $extensions[$this->handle->value]['pepper'] = $this->secrets->make();
        $config['extensions']                       = $extensions;

        $this->write($path, $config);
    }

    /**
     * The local config file's path within the site.
     */
    public function path(): string
    {
        return sprintf('%s/config/autoload/extensions/%s.local.php', $this->sitePath, $this->handle->value);
    }

    /**
     * What the local file returns now, or nothing when there is none. SiteConfig::read() has already refused a file
     * that doesn't return a list.
     *
     * @return array<array-key, mixed>
     *
     * @mago-expect analysis:mixed-assignment A config file returns whatever it returns.
     */
    private function current(string $path): array
    {
        $config = is_file($path) ? (require $path) : [];

        return is_array($config) ? $config : [];
    }

    /**
     * Writes the file whole, through a temporary file beside it, so a failed write leaves it as it was.
     *
     * @param array<array-key, mixed> $config
     *
     * @throws NotReady
     */
    private function write(string $path, array $config): void
    {
        $folder = dirname($path);
        if (! is_dir($folder) || ! is_writable($folder) || (is_file($path) && ! is_writable($path))) {
            throw NotReady::because(sprintf(
                'The CMS can\'t write %s, so no pepper was added. Add one by hand, as the fix\'s guide says.',
                $path,
            ));
        }

        $temporary = sprintf('%s/.%s.%s.tmp', $folder, $this->handle->value, uniqid());
        $contents  = sprintf(
            "<?php\n\n/**\n * The %s add-on's settings for this environment, never committed. The pepper was made by"
                . " the add-on's\n * setup check: keep it, and never change it once anyone is synced.\n */\n\n"
                . "declare(strict_types=1);\n\nreturn %s;\n",
            $this->handle->value,
            var_export($config, return: true),
        );
        if (false === file_put_contents($temporary, $contents) || ! rename($temporary, $path)) {
            throw NotReady::because(sprintf('%s couldn\'t be written, so no pepper was added.', $path));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, force: true);
        }
    }
}
