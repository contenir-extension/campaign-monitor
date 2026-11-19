<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Integration\Sync;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\CampaignMonitor\Sync\NotReady;
use Contenir\Extension\CampaignMonitor\Sync\PepperFile;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Contenir\Extension\CampaignMonitor\Sync\SiteConfig;
use Contenir\Extension\CampaignMonitor\Tests\Trait\UsesTemporarySiteTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_get_contents;
use function glob;
use function rmdir;
use function str_contains;
use function str_repeat;

/**
 * Adding a pepper to the add-on's local config file, in a site folder of the test's own.
 */
#[Group('integration')]
final class PepperFileTest extends TestCase
{
    use UsesTemporarySiteTrait;

    private const string PEPPER = 'an existing pepper, 32 bytes or more long';

    /**
     * @return array<string, array{string, array<array-key, mixed>}>
     */
    public static function alreadySetProvider(): array
    {
        $set = ['extensions' => ['campaign-monitor' => ['pepper' => self::PEPPER]]];

        return [
            'in the local file'  => ['local', $set],
            'in the global file' => ['global', $set],
        ];
    }

    private static function fresh(): string
    {
        return str_repeat('01', times: Secrets::BYTES);
    }

    #[Test]
    public function addsNothingWhenAFileCannotBeRead(): void
    {
        $this->configFile('global', ['extensions' => ['campaign-monitor' => 'not a list']]);

        $this->expectException(NotReady::class);
        $this->expectExceptionMessage('must return');

        $this->file()->add();
    }

    #[Test]
    public function addsNothingWhenTheConfigFolderCannotBeWritten(): void
    {
        chmod($this->extensions(), permissions: 0o555);

        try {
            $this->file()->add();
            static::fail('A pepper was added to a folder that cannot be written.');
        } catch (NotReady $e) {
            static::assertSame(
                [
                    "The CMS can't write {$this->file()->path()}, so no pepper was added. Add one by hand, as the"
                        . " fix's guide says.",
                    [],
                ],
                [$e->getMessage(), glob("{$this->extensions()}/*")],
            );
        }
    }

    #[Test]
    public function addsNothingWhenTheConfigFolderIsMissing(): void
    {
        rmdir($this->extensions());

        $this->expectException(NotReady::class);
        $this->expectExceptionMessage('can\'t write');

        $this->file()->add();
    }

    #[Test]
    public function addsToALocalFileWithoutTheAddOnsPart(): void
    {
        $this->configFile('local', ['elsewhere' => 'kept']);

        $this->file()->add();

        static::assertSame(
            ['elsewhere' => 'kept', 'extensions' => ['campaign-monitor' => ['pepper' => self::fresh()]]],
            require $this->file()->path(),
        );
    }

    #[Test]
    public function fillsAnEmptyPepper(): void
    {
        $this->configFile('local', ['extensions' => ['campaign-monitor' => ['pepper' => '']]]);

        $this->file()->add();

        static::assertSame(self::fresh(), $this->read()->pepper);
    }

    #[Test]
    public function keepsWhatTheLocalFileAlreadyHolds(): void
    {
        $this->configFile('local', [
            'extensions' => [
                'campaign-monitor' => ['bindings' => ['newsletter' => ['list' => 'test-list']]],
                'other-addon'      => ['setting' => 'kept'],
            ],
            'elsewhere'  => 'kept too',
        ]);

        $this->file()->add();

        static::assertSame(
            [
                'extensions' => [
                    'campaign-monitor' => [
                        'bindings' => ['newsletter' => ['list' => 'test-list']],
                        'pepper'   => self::fresh(),
                    ],
                    'other-addon'      => ['setting' => 'kept'],
                ],
                'elsewhere'  => 'kept too',
            ],
            require $this->file()->path(),
        );
    }

    #[Test]
    public function leavesALocalFileThatCannotBeWrittenAsItWas(): void
    {
        $this->configFile('local', ['elsewhere' => 'kept']);
        chmod($this->file()->path(), permissions: 0o444);

        try {
            $this->file()->add();
            static::fail('A pepper was added to a file that cannot be written.');
        } catch (NotReady $e) {
            static::assertSame(
                [true, ['elsewhere' => 'kept']],
                [str_contains($e->getMessage(), 'can\'t write'), require $this->file()->path()],
            );
        }
    }

    #[Test]
    public function makesTheLocalFileWithAFreshPepper(): void
    {
        $this->file()->add();

        static::assertEquals(new SiteConfig(self::fresh(), []), $this->read());
    }

    #[Test]
    public function namesTheLocalConfigFile(): void
    {
        static::assertSame(
            "{$this->site}/config/autoload/extensions/campaign-monitor.local.php",
            $this->file()->path(),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Test]
    #[DataProvider('alreadySetProvider')]
    public function neverReplacesAPepperAlreadySet(string $kind, array $config): void
    {
        $this->configFile($kind, $config);

        try {
            $this->file()->add();
            static::fail('A pepper already set was replaced.');
        } catch (NotReady $e) {
            static::assertSame(
                [
                    'A pepper is already set. It is never replaced, since a new one would forget everyone who'
                        . ' unsubscribed.',
                    self::PEPPER,
                ],
                [$e->getMessage(), $this->read()->pepper],
            );
        }
    }

    #[Test]
    public function saysInTheFileWhatThePepperIsAndToKeepIt(): void
    {
        $this->file()->add();

        static::assertStringStartsWith(
            "<?php\n\n/**\n * The campaign-monitor add-on's settings for this environment, never committed. The pepper"
                . " was made by the add-on's\n * setup check: keep it, and never change it once anyone is synced.\n"
                . " */\n\ndeclare(strict_types=1);\n\nreturn array (",
            (string) file_get_contents($this->file()->path()),
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpSite();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownSite();
    }

    private function file(): PepperFile
    {
        return new PepperFile(
            $this->site,
            new Handle('campaign-monitor'),
            new Secrets(static fn(int $bytes): string => str_repeat("\x01", $bytes)),
        );
    }

    private function read(): SiteConfig
    {
        return SiteConfig::read($this->site, new Handle('campaign-monitor'));
    }
}
