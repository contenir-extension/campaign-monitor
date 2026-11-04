<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\CampaignMonitor\CampaignMonitorExtension;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\Check\WebhookCheck;
use Contenir\Extension\CampaignMonitor\Operation\RegisterWebhooks;
use Contenir\Extension\CampaignMonitor\Operation\SyncNow;
use Contenir\Extension\CampaignMonitor\Operation\TestConnection;
use Contenir\Extension\CampaignMonitor\Schedule\Drain;
use Contenir\Extension\CampaignMonitor\Sync\DatabaseSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Database\SiteDatabase;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Webhook\EventsWebhook;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function dirname;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * The add-on on the API's in-memory host, over its tables on SQLite and a site's config file:
 * a bound section's save going through the outbox to Campaign Monitor, the webhook suppressing
 * whoever left, and its buttons, screens and checks as the CMS runs them. Campaign Monitor is a
 * fake HTTP client.
 */
#[Group('integration')]
final class InMemoryHostTest extends TestCase
{
    private const string SECTION = 'library.members.member';

    private const array SETTINGS = ['settings.integration.campaign-monitor'];

    private AdapterInterface $database;

    private FakeHttpClient $campaignMonitor;

    private InMemoryHost $host;

    #[Test]
    public function checksTheWebhooksAgainstCampaignMonitor(): void
    {
        static::assertSame(
            Status::Warning,
            $this->host->check('campaign-monitor', 'webhooks')->status,
        );
    }

    #[Test]
    public function refusesTheButtonsToSomeoneWhoMayOnlyView(): void
    {
        static::assertNull($this->host->operate(
            'campaign-monitor',
            'campaign-monitor',
            'sync-now',
            [],
            ['settings.integration.campaign-monitor:view'],
        ));
    }

    #[Test]
    public function registersTheWebhooksFromItsButton(): void
    {
        $this->answer(Replies::json(201, 'w1'), Replies::json(201, 'w2'));

        $result = $this->host->operate('campaign-monitor', 'campaign-monitor', 'register-webhooks', [], self::SETTINGS);

        static::assertSame(
            [
                ['Registered the webhook for newsletter.', 'Registered the webhook for members.'],
                [['binding' => 'members', 'remote_id' => 'w2'], ['binding' => 'newsletter', 'remote_id' => 'w1']],
            ],
            [
                [$result?->messages()[0]['text'], $result?->messages()[1]['text']],
                $this->rows('SELECT binding, remote_id FROM ext_campaign_monitor_webhook ORDER BY binding'),
            ],
        );
    }

    #[Test]
    public function sendsABoundSectionsSaveThroughTheOutbox(): void
    {
        $this->host->bind(self::SECTION, 'campaign-monitor', 'sync-record', 'RecordPostSave');
        $this->host->save(self::SECTION, null, ['email' => 'ada@example.com', 'newsletter' => '1', 'name' => 'Ada']);
        $queued = $this->rows('SELECT binding, action, email, state FROM ext_campaign_monitor_outbox');
        $this->answer(Replies::import());

        (new Drain(Http::connections($this->campaignMonitor)))->run($this->host->context('campaign-monitor'));

        static::assertSame(
            [
                [['binding' => 'members', 'action' => 'subscribe', 'email' => 'ada@example.com', 'state' => 'waiting']],
                '/api/v3.3/subscribers/members-test-list/import.json',
                [['state' => 'done', 'email' => null]],
            ],
            [
                $queued,
                $this->campaignMonitor->requests[0]->getUri()->getPath(),
                $this->rows('SELECT state, email FROM ext_campaign_monitor_outbox'),
            ],
        );
    }

    #[Test]
    public function showsTheSyncScreen(): void
    {
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/campaign-monitor/sync'),
            ['extension.campaign-monitor.sync'],
        );

        static::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function suppressesWhoeverLeftThroughTheWebhook(): void
    {
        $http     = new Psr17Factory();
        $response = $this->host->handle(
            $http->createServerRequest(
                'POST',
                'https://www.example.com/api/extension/campaign-monitor/events?binding=newsletter&key='
                    . Settings::SECRET,
            )
                ->withQueryParams(['binding' => 'newsletter', 'key' => Settings::SECRET])
                ->withBody($http->createStream(json_encode([
                    'ListID' => 'a1b2c3d4e5f6',
                    'Events' => [[
                        'Type'         => 'Deactivate',
                        'State'        => 'Unsubscribed',
                        'Date'         => '2026-11-04 09:30:00',
                        'EmailAddress' => 'ada@example.com',
                    ]],
                ], JSON_THROW_ON_ERROR))),
        );

        static::assertSame(
            [200, [['binding' => 'newsletter', 'reason' => 'unsubscribed']]],
            [$response->getStatusCode(), $this->rows('SELECT binding, reason FROM ext_campaign_monitor_suppression')],
        );
    }

    #[Test]
    public function testsTheConnectionFromItsButton(): void
    {
        $this->answer(Replies::client(), Replies::lists(['members-test-list' => 'Members (test)']));

        static::assertSame(
            'Connected to Studio Esteta. Its lists: Members (test).',
            $this->host
                ->operate('campaign-monitor', 'campaign-monitor', 'test-connection', [], self::SETTINGS)
                ?->messages()[0]['text'],
        );
    }

    protected function setUp(): void
    {
        $this->database = SiteDatabase::adapter();
        SiteDatabase::migrations($this->database)->runPending();
        $this->campaignMonitor = new FakeHttpClient();
        $connections           = Http::connections($this->campaignMonitor);
        $http                  = new Psr17Factory();
        $this->host            = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
            sitePath: dirname(__DIR__) . '/TestAsset/Site/full',
        );
        $this->host->boot(
            ['campaign-monitor' => new CampaignMonitorExtension()],
            ['campaign-monitor'],
            [
                DatabaseSyncFactory::DATABASE => $this->database,
                SiteSettingsInterface::class  => new MemorySiteSettings(['site' => [
                    'url' => 'https://www.example.com',
                ]]),
                TestConnection::class         => new TestConnection($connections),
                SyncNow::class                => new SyncNow($connections),
                RegisterWebhooks::class       => new RegisterWebhooks($connections),
                EventsWebhook::class          => new EventsWebhook($connections),
                WebhookCheck::class           => new WebhookCheck($connections),
            ],
        );
        $this->host
            ->context('campaign-monitor')
            ->settings()
            ->set([
                CampaignMonitorSection::API_KEY        => Apis::KEY,
                CampaignMonitorSection::CLIENT         => Apis::CLIENT,
                CampaignMonitorSection::WEBHOOK_SECRET => Settings::SECRET,
            ]);
    }

    private function answer(ResponseInterface ...$answers): void
    {
        $this->campaignMonitor->answer(...$answers);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(string $sql): array
    {
        $rows = [];
        foreach ($this->database->query($sql)->execute() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }
}
