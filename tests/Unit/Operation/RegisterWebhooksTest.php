<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Operation;

use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\CampaignMonitor\CampaignMonitorSection;
use Contenir\Extension\CampaignMonitor\Operation\RegisterWebhooks;
use Contenir\Extension\CampaignMonitor\Sync\Secrets;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function json_decode;
use function str_repeat;

#[Group('unit')]
final class RegisterWebhooksTest extends TestCase
{
    private const string MADE = 'abababababababababababababababababababababababababababababababab';

    private static function operation(FakeHttpClient $http, SyncKit $kit): RegisterWebhooks
    {
        return new RegisterWebhooks(
            Http::connections($http),
            $kit->factory,
            new Secrets(static fn(int $bytes): string => str_repeat("\xab", $bytes)),
        );
    }

    #[Test]
    public function deletesTheWebhookItRegisteredBeforeRegisteringAgain(): void
    {
        $kit  = new SyncKit();
        $http = new FakeHttpClient(Replies::ok(), Replies::json(201, 'w2'));
        $kit->webhooks->save(new RegisteredWebhook('newsletter', 'w1', null, $kit->clock->now()));

        self::operation($http, $kit)->handle(new FakeOperationContext(Contexts::make(Settings::connected())));

        static::assertSame(
            [
                ['DELETE', '/api/v3.3/lists/' . People::LIST . '/webhooks/w1.json'],
                ['POST', '/api/v3.3/lists/' . People::LIST . '/webhooks.json'],
                'w2',
            ],
            [
                [$http->requests[0]->getMethod(), $http->requests[0]->getUri()->getPath()],
                [$http->requests[1]->getMethod(), $http->requests[1]->getUri()->getPath()],
                $kit->webhooks->find('newsletter')?->remoteId,
            ],
        );
    }

    #[Test]
    public function keepsTheSecretItMadeTheFirstTime(): void
    {
        $settings = Settings::connected(secret: null);

        self::operation(new FakeHttpClient(Replies::json(201, 'w1')), new SyncKit())
            ->handle(
                new FakeOperationContext(Contexts::make($settings)),
            );

        static::assertSame(self::MADE, $settings->values[CampaignMonitorSection::WEBHOOK_SECRET]);
    }

    #[Test]
    public function keepsTheSigningSecretAndTimeOfEachRegistration(): void
    {
        $kit = new SyncKit();

        self::operation(new FakeHttpClient(Replies::json(201, 'w1')), $kit)
            ->handle(
                new FakeOperationContext(Contexts::make(Settings::connected())),
            );

        static::assertEquals(
            new RegisteredWebhook('newsletter', 'w1', null, $kit->clock->now()),
            $kit->webhooks->find('newsletter'),
        );
    }

    #[Test]
    public function registersEachBindingAtTheEventsWebhookWithTheSecret(): void
    {
        $kit  = new SyncKit([...SyncKit::NEWSLETTER, ...SyncKit::MEMBERS]);
        $http = new FakeHttpClient(Replies::json(201, 'w1'), Replies::json(201, 'w2'));

        $result = self::operation($http, $kit)->handle(new FakeOperationContext(Contexts::make(Settings::connected())));

        static::assertSame(
            [
                [
                    ['level' => 'success', 'text' => 'Registered the webhook for newsletter.'],
                    ['level' => 'success', 'text' => 'Registered the webhook for members.'],
                ],
                'https://www.example.com/api/extension/campaign-monitor/events?binding=members&key=' . Settings::SECRET,
                ['newsletter' => 'w1', 'members' => 'w2'],
            ],
            [
                $result->messages(),
                json_decode((string) $http->requests[1]->getBody(), associative: true)['Url'],
                array_map(
                    static fn(RegisteredWebhook $webhook): string => $webhook->remoteId,
                    $kit->webhooks->webhooks,
                ),
            ],
        );
    }

    #[Test]
    public function registersTheOthersWhenOneIsRefused(): void
    {
        $kit  = new SyncKit([...SyncKit::NEWSLETTER, ...SyncKit::MEMBERS]);
        $http = new FakeHttpClient(Replies::error(400, 101, 'Invalid ListID'), Replies::json(201, 'w2'));

        $result = self::operation($http, $kit)->handle(new FakeOperationContext(Contexts::make(Settings::connected())));

        static::assertSame(
            [
                [
                    [
                        'level' => 'danger',
                        'text'  => 'The webhook for newsletter wasn\'t registered. The service refused the'
                            . ' connection: Invalid ListID',
                    ],
                    ['level' => 'success', 'text' => 'Registered the webhook for members.'],
                ],
                ['members'],
            ],
            [$result->messages(), array_keys($kit->webhooks->webhooks)],
        );
    }

    #[Test]
    public function saysWhenThereAreNoBindings(): void
    {
        $result = self::operation(new FakeHttpClient(), new SyncKit([]))
            ->handle(
                new FakeOperationContext(Contexts::make(Settings::connected())),
            );

        static::assertSame(
            [['level' => 'warning', 'text' => 'There are no bindings to register webhooks for.']],
            $result->messages(),
        );
    }

    #[Test]
    public function saysWhenTheSecretCannotBeSaved(): void
    {
        $settings = new ArraySettings([...Settings::connected(secret: null)->values], refuse: 'Read-only settings.');

        $result = self::operation(new FakeHttpClient(), new SyncKit())
            ->handle(
                new FakeOperationContext(Contexts::make($settings)),
            );

        static::assertSame(
            [['level' => 'danger', 'text' => 'The webhook\'s secret couldn\'t be saved: Read-only settings.']],
            $result->messages(),
        );
    }

    #[Test]
    public function saysWhenTheSitesAddressIsNotSet(): void
    {
        $http   = new FakeHttpClient();
        $result = self::operation($http, new SyncKit())
            ->handle(new FakeOperationContext(Contexts::make(
                Settings::connected(),
                [SiteSettingsInterface::class => new MemorySiteSettings()],
            )));

        static::assertSame(
            [
                [[
                    'level' => 'warning',
                    'text'  => 'Set the site\'s address in Site Settings first: Campaign Monitor calls the webhook'
                        . ' there.',
                ]],
                [],
            ],
            [$result->messages(), $http->requests],
        );
    }
}
