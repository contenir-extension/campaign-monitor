<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Schedule;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\CampaignMonitor\Schedule\Drain;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function count;

#[Group('unit')]
final class DrainTest extends TestCase
{
    private static function subscribe(SyncKit $kit, string $email): void
    {
        $kit->sync->audience()->subscribe(
            'newsletter',
            ['email' => $email],
            new Consent('Signing up to the newsletter', ConsentSource::Form, $kit->clock->now()),
        );
    }

    #[Test]
    public function deletesMessagesKeptPastTheirTime(): void
    {
        $kit    = new SyncKit();
        $logger = new MemoryLogger();
        self::subscribe($kit, 'ada@example.com');
        $kit->stores->outbox->done(1, $kit->clock->now()->modify('-8 days'));

        (new Drain(Http::connections(new FakeHttpClient()), $kit->factory))->run(
            Contexts::make(Settings::connected(), logger: $logger),
        );

        static::assertSame(
            [[], ['Deleted {count} messages kept past their time'], [['count' => 1]]],
            [
                $kit->stores->outbox->rows,
                array_column($logger->entries(), 'message'),
                array_column($logger->entries(), 'context'),
            ],
        );
    }

    #[Test]
    public function doesNothingUntilTheConnectionIsSaved(): void
    {
        $kit  = new SyncKit();
        $http = new FakeHttpClient();
        self::subscribe($kit, 'ada@example.com');

        (new Drain(Http::connections($http), $kit->factory))->run(Contexts::make(new ArraySettings()));

        static::assertSame([], $http->requests);
    }

    #[Test]
    public function doesNothingUntilTheSiteIsSetUp(): void
    {
        $logger = new MemoryLogger();

        (new Drain(Http::connections(new FakeHttpClient()), FakeSyncFactory::notReady()))->run(
            Contexts::make(Settings::connected(), logger: $logger),
        );

        static::assertSame([], $logger->entries());
    }

    #[Test]
    public function logsNothingWhenNothingWasDue(): void
    {
        $logger = new MemoryLogger();

        (new Drain(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->run(
            Contexts::make(Settings::connected(), logger: $logger),
        );

        static::assertSame([], $logger->entries());
    }

    #[Test]
    public function sendsWhatIsDueAndLogsWhatBecameOfIt(): void
    {
        $kit    = new SyncKit();
        $http   = new FakeHttpClient(Replies::import());
        $logger = new MemoryLogger();
        self::subscribe($kit, 'ada@example.com');
        self::subscribe($kit, 'grace@example.com');

        (new Drain(Http::connections($http), $kit->factory))->run(
            Contexts::make(Settings::connected(), logger: $logger),
        );

        static::assertSame(
            [1, ['Sent 2; 0 needed nothing; 0 will be tried again; 0 failed; 0 ran out of retries.']],
            [count($http->requests), array_column($logger->entries(), 'message')],
        );
    }
}
