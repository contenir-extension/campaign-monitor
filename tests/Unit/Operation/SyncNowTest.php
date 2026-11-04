<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Operation;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Extension\CampaignMonitor\Operation\SyncNow;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[Group('unit')]
final class SyncNowTest extends TestCase
{
    #[Test]
    public function asksForTheConnectionFirst(): void
    {
        $result = (new SyncNow(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            new FakeOperationContext(Contexts::make(new ArraySettings())),
        );

        static::assertSame(
            [['level' => 'warning', 'text' => 'Enter the API key, and save, first.']],
            $result->messages(),
        );
    }

    #[Test]
    public function saysWhatTheSiteStillNeedsToSync(): void
    {
        $result = (new SyncNow(Http::connections(new FakeHttpClient()), FakeSyncFactory::notReady('No pepper.')))
            ->handle(new FakeOperationContext(Contexts::make(Settings::connected())));

        static::assertSame([['level' => 'warning', 'text' => 'No pepper.']], $result->messages());
    }

    #[Test]
    public function saysWhenTheCmsGivesNoAddOnContext(): void
    {
        static::assertSame(
            'danger',
            (new SyncNow())->handle(new FakeOperationContext(null))
                ->messages()[0]['level'],
        );
    }

    #[Test]
    public function sendsWhatIsWaitingAtOnce(): void
    {
        $kit  = new SyncKit();
        $http = new FakeHttpClient(Replies::import());
        $kit->sync->audience()->subscribe(
            'newsletter',
            ['email' => 'ada@example.com', 'name' => 'Ada'],
            new Consent('Signing up to the newsletter', ConsentSource::Form, $kit->clock->now()),
        );

        $result = (new SyncNow(Http::connections($http), $kit->factory))->handle(
            new FakeOperationContext(Contexts::make(Settings::connected())),
        );

        static::assertSame(
            [
                'success',
                'Sent 1; 0 needed nothing; 0 will be tried again; 0 failed; 0 ran out of retries.',
                1,
            ],
            [$result->messages()[0]['level'], $result->messages()[0]['text'], count($http->requests)],
        );
    }
}
