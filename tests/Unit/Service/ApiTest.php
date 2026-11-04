<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit\Service;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\CampaignMonitor\Service\Api;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\NetworkError;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[Group('unit')]
final class ApiTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, int|null}>
     */
    public static function waitProvider(): array
    {
        return [
            'Retry-After'             => [['Retry-After' => '30'], 30],
            'the rate limit\'s reset' => [['X-RateLimit-Reset' => '45'], 45],
            'Retry-After first'       => [['Retry-After' => '30', 'X-RateLimit-Reset' => '45'], 30],
            'a date it can\'t count'  => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null],
            'no wait asked'           => [[], null],
        ];
    }

    #[Test]
    public function answersARequestThatNeverArrivedWithWhy(): void
    {
        $reply = Apis::over(new FakeHttpClient(new NetworkError('Connection timed out')))->send('GET', 'clients.json');

        static::assertSame(
            [0, 'Campaign Monitor couldn\'t be reached: Connection timed out'],
            [$reply->status, $reply->message()],
        );
    }

    #[Test]
    public function connectsThroughGuzzleUnlessGivenAnotherClient(): void
    {
        $connection = new Connection(Apis::KEY, Apis::CLIENT);

        static::assertSame($connection, (new Api($connection))->connection);
    }

    #[Test]
    #[DataProvider('waitProvider')]
    public function readsHowLongCampaignMonitorAskedToWait(array $headers, ?int $wait): void
    {
        $reply = Apis::over(new FakeHttpClient(Replies::error(429, 429, 'Rate limit exceeded', $headers)))->send(
            'GET',
            'clients.json',
        );

        static::assertSame($wait, $reply->retryAfter);
    }

    #[Test]
    public function readsNoBodyFromAReplyThatIsNotJson(): void
    {
        $reply = Apis::over(new FakeHttpClient(new Response(502, [], '<html>Bad gateway</html>')))->send(
            'GET',
            'clients.json',
        );

        static::assertNull($reply->body);
    }

    #[Test]
    public function readsTheReply(): void
    {
        $reply = Apis::over(new FakeHttpClient(Replies::json(201, 'webhook-id')))->send(
            'POST',
            'lists/a1/webhooks.json',
            [],
        );

        static::assertSame([201, 'webhook-id'], [$reply->status, $reply->body]);
    }

    #[Test]
    public function sendsTheBodyAsJson(): void
    {
        $http = new FakeHttpClient(Replies::ok());
        Apis::over($http)->send('POST', 'subscribers/a1/unsubscribe.json', [
            'Url'  => 'https://example.com/a/b',
            'Name' => 'Zoë',
        ]);

        static::assertSame(
            ['application/json', '{"Url":"https://example.com/a/b","Name":"Zoë"}'],
            [$http->requests[0]->getHeaderLine('Content-Type'), (string) $http->requests[0]->getBody()],
        );
    }

    #[Test]
    public function sendsToCampaignMonitorsApiWithTheQuery(): void
    {
        $http = new FakeHttpClient(Replies::ok());
        Apis::over($http)->send('DELETE', 'subscribers/a1.json', query: ['email' => 'ada+news@example.com']);

        static::assertSame(
            ['DELETE', 'https://api.createsend.com/api/v3.3/subscribers/a1.json?email=ada%2Bnews%40example.com'],
            [$http->requests[0]->getMethod(), (string) $http->requests[0]->getUri()],
        );
    }

    #[Test]
    public function signsInAndAsksForJson(): void
    {
        $http = new FakeHttpClient(Replies::ok());
        Apis::over($http)->send('GET', 'clients.json');

        static::assertSame(
            [
                'Basic ' . base64_encode(Apis::KEY . ':x'),
                'application/json',
                '',
                'https://api.createsend.com/api/v3.3/clients.json',
            ],
            [
                $http->requests[0]->getHeaderLine('Authorization'),
                $http->requests[0]->getHeaderLine('Accept'),
                $http->requests[0]->getHeaderLine('Content-Type'),
                (string) $http->requests[0]->getUri(),
            ],
        );
    }

    #[Test]
    public function stopsWhenTheKeyIsRefused(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Invalid API key');

        Apis::over(new FakeHttpClient(Replies::error(401, 100, 'Invalid API key')))->send('GET', 'clients.json');
    }

    #[Test]
    public function stopsWhenTheKeyLacksAccess(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Agency only');

        Apis::over(new FakeHttpClient(Replies::error(403, 403, 'Agency only')))->send('GET', 'clients.json');
    }
}
