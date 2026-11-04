<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Tests\TestAsset\Factory;

use Contenir\Extension\CampaignMonitor\Connections;
use Contenir\Extension\CampaignMonitor\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Connections to Campaign Monitor over a fake HTTP client.
 */
final class Http
{
    public static function connections(FakeHttpClient $http): Connections
    {
        $factory = new Psr17Factory();

        return new Connections($http, $factory, $factory);
    }
}
