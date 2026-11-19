<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Handler;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\CampaignMonitor\Handler\ClientsLookup;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\NetworkError;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Settings\ArraySettings;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class ClientsLookupTest extends TestCase
{
    private const string URL = '/admin/extension/campaign-monitor/clients';

    private const string TYPED = 'typed-test-key';

    private static function ask(
        FakeHttpClient $http,
        ArraySettings $settings = new ArraySettings(),
        string $key = '',
        string $method = 'GET',
    ): HandlerResult {
        $request = new ServerRequest($method, self::URL);
        if ('' !== $key) {
            $request = $request->withHeader(ClientsLookup::KEY_HEADER, $key);
        }

        return (new ClientsLookup(Http::connections($http)))->handle($request, Contexts::make($settings));
    }

    /**
     * @return list<string>
     */
    private static function authorizations(FakeHttpClient $http): array
    {
        return array_map(static fn($request): string => $request->getHeaderLine('Authorization'), $http->requests);
    }

    #[Test]
    public function answersOnlyGet(): void
    {
        $http = new FakeHttpClient();

        $result = self::ask($http, Settings::keyOnly(), self::TYPED, 'POST');

        static::assertSame(
            [405, ['ok' => false, 'error' => 'Ask for the clients with GET.'], []],
            [$result->status, $result->json, $http->requests],
        );
    }

    #[Test]
    public function asksForAKeyWhenNoneIsTypedOrSaved(): void
    {
        $http = new FakeHttpClient();

        $result = self::ask($http, key: '   ');

        static::assertSame(
            [400, ['ok' => false, 'error' => 'Enter the API key.'], []],
            [$result->status, $result->json, $http->requests],
        );
    }

    #[Test]
    public function asksWithTheSavedKeyWhenNoneIsTyped(): void
    {
        $http = new FakeHttpClient(Replies::clients(['c1' => 'Studio Esteta']));

        $result = self::ask($http, Settings::keyOnly());

        static::assertSame(
            [
                [(new Connection(Apis::KEY, ''))->authorization()],
                [
                    'ok'      => true,
                    'clients' => [
                        ['id' => 'c1', 'name' => 'Studio Esteta'],
                    ],
                ],
            ],
            [self::authorizations($http), $result->json],
        );
    }

    #[Test]
    public function asksWithTheTypedKeyRatherThanTheSavedOne(): void
    {
        $http = new FakeHttpClient(Replies::clients([]));

        self::ask($http, Settings::connected(), self::TYPED);

        static::assertSame([(new Connection(self::TYPED, ''))->authorization()], self::authorizations($http));
    }

    #[Test]
    public function listsTheClientsATypedKeyReaches(): void
    {
        $http = new FakeHttpClient(Replies::clients(['c1' => 'Studio Esteta', 'c2' => 'Sector7g']));

        $result = self::ask($http, key: ' ' . self::TYPED . ' ');

        static::assertSame(
            [
                ResultKind::Json,
                200,
                [
                    'ok'      => true,
                    'clients' => [
                        ['id' => 'c1', 'name' => 'Studio Esteta'],
                        ['id' => 'c2', 'name' => 'Sector7g'],
                    ],
                ],
            ],
            [$result->kind, $result->status, $result->json],
        );
    }

    #[Test]
    public function saysWhenCampaignMonitorCannotBeReached(): void
    {
        $result = self::ask(new FakeHttpClient(new NetworkError('Connection timed out')), key: self::TYPED);

        static::assertSame(
            [
                422,
                [
                    'ok'    => false,
                    'error' => "The service refused the connection: Campaign Monitor couldn't be reached: Connection timed out",
                ],
            ],
            [$result->status, $result->json],
        );
    }

    #[Test]
    public function saysWhyCampaignMonitorRefusedTheKey(): void
    {
        $result = self::ask(new FakeHttpClient(Replies::refused()), key: self::TYPED);

        static::assertSame(
            [
                422,
                [
                    'ok'    => false,
                    'error' => 'The service refused the connection: Must supply a valid HTTP Basic Authorization header',
                ],
            ],
            [$result->status, $result->json],
        );
    }
}
