<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Extension\Api\Handler\WebhookResponse;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use Contenir\Extension\CampaignMonitor\Webhook\EventsWebhook;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class EventsWebhookTest extends TestCase
{
    private const string URL = 'https://www.example.com/api/extension/campaign-monitor/events';

    /**
     * @return array<string, array{array<string, string>, ArraySettings}>
     */
    public static function unverifiedProvider(): array
    {
        return [
            'no key'              => [['binding' => 'newsletter'], Settings::connected()],
            'an empty key'        => [['binding' => 'newsletter', 'key' => ''], Settings::connected()],
            'the wrong key'       => [['binding' => 'newsletter', 'key' => 'guess'], Settings::connected()],
            'no secret saved'     => [['binding' => 'newsletter', 'key' => ''], Settings::connected(secret: null)],
            'a key but no secret' => [['binding' => 'newsletter', 'key' => 'guess'], Settings::connected(secret: null)],
        ];
    }

    /**
     * @return array{int, string}
     */
    private static function answer(WebhookResponse $response): array
    {
        return [$response->status, $response->body];
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $body
     */
    private static function call(
        array $query = ['binding' => 'newsletter', 'key' => Settings::SECRET],
        array $body = [],
    ): ServerRequest {
        return (new ServerRequest('POST', self::URL, ['Content-Type' => 'application/json'], json_encode(
            $body,
            JSON_THROW_ON_ERROR,
        )))->withQueryParams($query);
    }

    #[Test]
    public function acceptsACallCarryingTheSecret(): void
    {
        static::assertTrue((new EventsWebhook())->verify(self::call(), Contexts::make(Settings::connected())));
    }

    #[Test]
    public function answersNotFoundForABindingItDoesNotHave(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            self::call(['binding' => 'members', 'key' => Settings::SECRET]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame([404, 'No such binding.'], self::answer($response));
    }

    #[Test]
    public function answersUnavailableUntilConnected(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            self::call(),
            Contexts::make(new ArraySettings()),
        );

        static::assertSame([503, 'The add-on isn\'t connected to Campaign Monitor.'], self::answer($response));
    }

    #[Test]
    public function answersUnavailableUntilTheSiteIsSetUp(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), FakeSyncFactory::notReady()))->handle(
            self::call(),
            Contexts::make(Settings::connected()),
        );

        static::assertSame([503, 'The site isn\'t set up to sync yet.'], self::answer($response));
    }

    #[Test]
    public function refusesACallForAnotherList(): void
    {
        $kit      = new SyncKit();
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), $kit->factory))->handle(
            self::call(body: ['ListID' => 'another', 'Events' => []]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [400, 'The webhook call was refused: it isn\'t for the binding\'s list'],
            self::answer($response),
        );
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('unverifiedProvider')]
    #[Test]
    public function refusesACallWithoutTheSecret(array $query, ArraySettings $settings): void
    {
        static::assertFalse((new EventsWebhook())->verify(self::call($query), Contexts::make($settings)));
    }

    #[Test]
    public function suppressesWhoeverLeftTheList(): void
    {
        $kit      = new SyncKit();
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), $kit->factory))->handle(
            self::call(body: [
                'ListID' => People::LIST,
                'Events' => [[
                    'Type'         => 'Deactivate',
                    'State'        => 'Unsubscribed',
                    'Date'         => '2026-11-04 09:30:00',
                    'EmailAddress' => 'ada@example.com',
                ]],
            ]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [[200, '{"received":1}'], SuppressionReason::Unsubscribed],
            [
                self::answer($response),
                $kit->stores->suppression->reason(
                    'newsletter',
                    $kit->stores->hasher->email(Email::fromString('ada@example.com')),
                ),
            ],
        );
    }
}
