<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Integration\Check;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\CampaignMonitor\Check\AddPepper;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Contenir\Extension\CampaignMonitor\Sync\SiteConfig;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\Trait\UsesTemporarySiteTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The setup check's one-click fix, run against a site folder of the test's own.
 */
#[Group('integration')]
final class AddPepperTest extends TestCase
{
    use UsesTemporarySiteTrait;

    #[Test]
    public function savesAFreshPepperWhereTheCmsAndTheSiteReadIt(): void
    {
        $result = $this->fix()->apply([], Contexts::make(sitePath: $this->site));

        static::assertSame(
            [
                true,
                "Made a pepper and saved it in {$this->extensions()}/campaign-monitor.local.php. Keep that file when you"
                    . ' deploy, and copy the pepper to any other environment that syncs the same lists.',
                str_repeat('01', times: Secrets::BYTES),
            ],
            [
                $result->applied,
                $result->message,
                SiteConfig::read($this->site, new Handle('campaign-monitor'))->pepper,
            ],
        );
    }

    #[Test]
    public function saysWhyWhenNoPepperCanBeAdded(): void
    {
        $this->configFile('local', [
            'extensions' => ['campaign-monitor' => ['pepper' => 'one already set, 32 bytes long']],
        ]);

        $result = $this->fix()->apply([], Contexts::make(sitePath: $this->site));

        static::assertSame(
            [
                false,
                'A pepper is already set. It is never replaced, since a new one would forget everyone who unsubscribed.',
            ],
            [$result->applied, $result->message],
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

    private function fix(): AddPepper
    {
        return new AddPepper(new Secrets(static fn(int $bytes): string => str_repeat("\x01", $bytes)));
    }
}
