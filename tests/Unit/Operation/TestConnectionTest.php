<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Operation;

use Contenir\Extension\CampaignMonitor\Operation\TestConnection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TestConnectionTest extends TestCase
{
    /**
     * @return list<array{level: string, text: string}>
     */
    private static function messages(FakeHttpClient $http, ArraySettings $settings): array
    {
        return (new TestConnection(Http::connections($http)))->handle(new FakeOperationContext(Contexts::make(
            $settings,
        )))
            ->messages();
    }

    #[Test]
    public function asksForTheKeyFirst(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'Enter the API key, and save, first.']],
            self::messages(new FakeHttpClient(), new ArraySettings()),
        );
    }

    #[Test]
    public function listsTheClientsToChooseFromBeforeOneIsChosen(): void
    {
        static::assertSame(
            [[
                'level' => 'warning',
                'text'  => 'The key works. Choose the client whose lists the site keeps, and save: Studio Esteta,'
                    . ' Sector7g.',
            ]],
            self::messages(
                new FakeHttpClient(Replies::clients(['c1' => 'Studio Esteta', 'c2' => 'Sector7g'])),
                Settings::keyOnly(),
            ),
        );
    }

    #[Test]
    public function namesTheClientAndItsLists(): void
    {
        static::assertSame(
            [['level' => 'success', 'text' => 'Connected to Studio Esteta. Its lists: Newsletter, Test list.']],
            self::messages(
                new FakeHttpClient(Replies::client(), Replies::lists(['l1' => 'Newsletter', 'l2' => 'Test list'])),
                Settings::connected(),
            ),
        );
    }

    #[Test]
    public function saysWhenCampaignMonitorRefusesTheKey(): void
    {
        static::assertSame(
            [[
                'level' => 'danger',
                'text'  => 'The service refused the connection: Must supply a valid HTTP Basic Authorization header',
            ]],
            self::messages(new FakeHttpClient(Replies::refused()), Settings::connected()),
        );
    }

    #[Test]
    public function saysWhenTheClientHasNoListsYet(): void
    {
        static::assertSame(
            [['level' => 'success', 'text' => 'Connected to Studio Esteta. It has no lists yet.']],
            self::messages(new FakeHttpClient(Replies::client(), Replies::lists([])), Settings::connected()),
        );
    }

    #[Test]
    public function saysWhenTheCmsGivesNoAddOnContext(): void
    {
        static::assertSame(
            'danger',
            (new TestConnection())->handle(new FakeOperationContext(null))
                ->messages()[0]['level'],
        );
    }

    #[Test]
    public function saysWhenTheKeyReachesNoClients(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'The key works, but reaches no clients.']],
            self::messages(new FakeHttpClient(Replies::clients([])), Settings::keyOnly()),
        );
    }
}
