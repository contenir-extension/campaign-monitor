<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\CopyText;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\OneClickFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\CampaignMonitor\Check\AddPepper;
use Contenir\Extension\CampaignMonitor\Check\SetupCheck;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[Group('unit')]
final class SetupCheckTest extends TestCase
{
    #[Test]
    public function countsTheBindingsSet(): void
    {
        $one  = (new SetupCheck((new SyncKit())->factory))->check(Contexts::make());
        $both = (new SetupCheck((new SyncKit([...SyncKit::NEWSLETTER, ...SyncKit::MEMBERS]))->factory))->check(
            Contexts::make(),
        );

        static::assertSame(
            [
                [Status::Success, '1 binding set.'],
                [Status::Success, '2 bindings set.'],
            ],
            [[$one->status, $one->message], [$both->status, $both->message]],
        );
    }

    #[Test]
    public function guidesThroughTheConfigFileWhenSomethingElseIsWrong(): void
    {
        $result = (new SetupCheck(
            FakeSyncFactory::notReady('The bindings in the add-on\'s config file must be a list.'),
            new Secrets(static fn(int $bytes): string => str_repeat("\x01", $bytes)),
        ))->check(Contexts::make());
        $fix = $result->fix;

        static::assertEquals(
            [
                Status::Failure,
                'The bindings in the add-on\'s config file must be a list.',
                'How to set up the config file',
                [new CopyText('A new pepper', str_repeat('01', times: 32))],
                'The site\'s config/autoload/extensions/campaign-monitor.global.php holds the bindings, and'
                    . ' campaign-monitor.local.php the pepper, each as [\'extensions\' => [\'campaign-monitor\' =>'
                    . ' [...]]]. The pepper must be the same for the CMS and the site, and never change once anyone is'
                    . ' synced.',
            ],
            [
                $result->status,
                $result->message,
                $fix instanceof GuidedFix ? $fix->label : null,
                $fix instanceof GuidedFix ? $fix->copy : null,
                $fix instanceof GuidedFix ? $fix->explanation : null,
            ],
        );
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Campaign Monitor setup', (new SetupCheck())->label());
    }

    #[Test]
    public function makesAPepperWithOneClickWhenNoneIsSet(): void
    {
        $result = (new SetupCheck(FakeSyncFactory::notReady()))->check(Contexts::make());

        static::assertEquals(
            [
                Status::Failure,
                'No pepper is set, so sign-ups can\'t be synced yet. The pepper is a secret that keeps the addresses of'
                    . ' people who unsubscribed unreadable in the site\'s database.',
                new OneClickFix('Make a pepper', AddPepper::class),
            ],
            [$result->status, $result->message, $result->fix],
        );
    }

    #[Test]
    public function warnsWhenNoBindingsAreSet(): void
    {
        $result = (new SetupCheck((new SyncKit([]))->factory))->check(Contexts::make());

        static::assertSame(
            [Status::Warning, 'The add-on\'s config file sets no bindings, so nothing is synced.'],
            [$result->status, $result->message],
        );
    }
}
