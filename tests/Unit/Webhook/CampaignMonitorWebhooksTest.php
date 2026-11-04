<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Webhook;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Audience\Service\WebhookRegistration;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Webhook\CampaignMonitorWebhooks;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class CampaignMonitorWebhooksTest extends TestCase
{
    private const string URL = 'https://www.example.com/api/extension/campaign-monitor/events/s3cr3t';

    /**
     * @param array<string, mixed> $payload
     */
    private static function call(array $payload): ServerRequest
    {
        return new ServerRequest(
            'POST',
            self::URL,
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private static function webhooks(): CampaignMonitorWebhooks
    {
        return new CampaignMonitorWebhooks(Apis::over(new FakeHttpClient()));
    }

    #[Test]
    public function readsNoEventsFromACallWithoutAny(): void
    {
        static::assertSame([], self::webhooks()->parse(self::call(['ListID' => People::LIST]), People::binding()));
    }

    #[Test]
    public function readsTheEventsItCanActOnAndPassesOverTheRest(): void
    {
        $events = self::webhooks()
            ->parse(self::call([
                'ListID' => People::LIST,
                'Events' => [
                    ['Type' => 'Subscribe', 'Date' => '2026-11-04 09:00:00', 'EmailAddress' => 'grace@example.com'],
                    [
                        'Type'         => 'Deactivate',
                        'State'        => 'Unsubscribed',
                        'Date'         => '2026-11-04 09:30:00',
                        'EmailAddress' => 'ada@example.com',
                    ],
                    'not an event',
                    [
                        'Type'         => 'Deactivate',
                        'State'        => 'Bounced',
                        'Date'         => '2026-11-04 10:00:00',
                        'EmailAddress' => 'alan@example.com',
                    ],
                ],
            ]), People::binding());

        static::assertSame(
            [
                [RemoteEventKind::Unsubscribed, 'ada@example.com'],
                [RemoteEventKind::Cleaned,      'alan@example.com'],
            ],
            array_map(static fn($event): array => [$event->kind, $event->email->value], $events),
        );
    }

    #[Test]
    public function refusesACallForAnotherList(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: it isn\'t for the binding\'s list');

        self::webhooks()->parse(self::call(['ListID' => 'another', 'Events' => []]), People::binding());
    }

    #[Test]
    public function refusesACallThatIsNotAPost(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: Campaign Monitor sends its events by POST');

        self::webhooks()->parse(new ServerRequest('GET', self::URL), People::binding());
    }

    #[Test]
    public function refusesACallThatIsNotJson(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: the body isn\'t JSON');

        self::webhooks()->parse(new ServerRequest('POST', self::URL, [], 'ListID=a1'), People::binding());
    }

    #[Test]
    public function refusesARegistrationAnsweredWithSomethingOtherThanAnId(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage(
            'The service refused the connection: Campaign Monitor didn\'t give the webhook an id',
        );

        (new CampaignMonitorWebhooks(Apis::over(new FakeHttpClient(Replies::json(201, ['WebhookID' => 'wh-1'])))))
            ->register(People::binding(), self::URL);
    }

    #[Test]
    public function refusesARegistrationCampaignMonitorTurnedDown(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Invalid URL');

        (new CampaignMonitorWebhooks(Apis::over(new FakeHttpClient(Replies::error(400, 600, 'Invalid URL')))))
            ->register(People::binding(), self::URL);
    }

    #[Test]
    public function refusesARegistrationWithoutAnId(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage(
            'The service refused the connection: Campaign Monitor didn\'t give the webhook an id',
        );

        (new CampaignMonitorWebhooks(Apis::over(new FakeHttpClient(Replies::json(201, '')))))
            ->register(People::binding(), self::URL);
    }

    #[Test]
    public function registersForDeactivationsAndUpdatesAsJson(): void
    {
        $http         = new FakeHttpClient(Replies::json(201, 'wh-1'));
        $registration = (new CampaignMonitorWebhooks(Apis::over($http)))->register(People::binding(), self::URL);

        static::assertEquals(
            [
                'https://api.createsend.com/api/v3.3/lists/' . People::LIST . '/webhooks.json',
                ['Events' => ['Deactivate', 'Update'], 'Url' => self::URL, 'PayloadFormat' => 'json'],
                new WebhookRegistration('wh-1'),
            ],
            [
                (string) $http->requests[0]->getUri(),
                json_decode((string) $http->requests[0]->getBody(), associative: true, flags: JSON_THROW_ON_ERROR),
                $registration,
            ],
        );
    }
}
