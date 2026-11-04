<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\Unit;

use Contenir\Audience\Binding\Binding;
use Contenir\Extension\CampaignMonitor\Service\Connection;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Http;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\People;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionsTest extends TestCase
{
    #[Test]
    public function givesAnAdapterOverItsHttpClient(): void
    {
        $http = new FakeHttpClient(Replies::client());

        Http::connections($http)->adapter(new Connection(Apis::KEY, Apis::CLIENT))->verifyConnection();

        static::assertSame(
            'https://api.createsend.com/api/v3.3/clients/' . Apis::CLIENT . '.json',
            (string) $http->requests[0]->getUri(),
        );
    }

    #[Test]
    public function givesAnApiForTheConnection(): void
    {
        static::assertSame(
            Apis::CLIENT,
            Http::connections(new FakeHttpClient())->api(new Connection(Apis::KEY, Apis::CLIENT))->connection->clientId,
        );
    }

    #[Test]
    public function givesWebhooksOverItsHttpClient(): void
    {
        $http = new FakeHttpClient(Replies::json(201, 'w1'));

        $registration = Http::connections($http)
            ->webhooks(new Connection(Apis::KEY, Apis::CLIENT))
            ->register(new Binding('newsletter', People::LIST), 'https://www.example.com/hook');

        static::assertSame('w1', $registration->id);
    }
}
